/**
 * NSC Presale → Owner controls. Sends owner-only transactions to the presale contract
 * from the connected wallet (MetaMask or any browser wallet).
 */
(function () {
	"use strict";
	var C = window.NSC_ADMIN || {};
	var root = document.getElementById("nsc-owner");
	if (!root) return;
	var SEL = {
		setPriceUsd: "0x29927765",
		setSaleActive: "0x841718a6",
		setTreasury: "0xf0f44260",
		setLimits: "0xc4590d3f",
		withdrawUnsold: "0x1923cc1f",
		owner: "0x8da5cb5b",
		bnbUsd: "0x58614dbf"
	};
	var ERR = {
		"0x118cdaa7": "Only the owner wallet can do this. Switch MetaMask to the owner (deployer) account.",
		"0x7c946ed7": "That value can't be zero.",
		"0x89051165": "Renouncing ownership is disabled."
	};
	var provider = window.ethereum || null;
	var account = null;
	var owner = null;
	var q = function (a) { return root.querySelector('[data-a="' + a + '"]'); };

	function word(hex) { return hex.replace(/^0x/, "").toLowerCase().padStart(64, "0"); }
	function units(str) {
		str = String(str || "").trim();
		if (!/^\d*\.?\d*$/.test(str) || str === "" || str === ".") return null;
		var p = str.split(".");
		return BigInt(p[0] || "0") * 10n ** 18n + BigInt(((p[1] || "") + "000000000000000000").slice(0, 18));
	}
	function isAddr(a) { return /^0x[0-9a-fA-F]{40}$/.test(a || ""); }

	function msg(text, kind) {
		var el = q("msg");
		el.hidden = !text;
		el.className = "nsc-msg notice notice-" + (kind || "info");
		el.innerHTML = "<p>" + text + "</p>";
	}

	function ensureChain() {
		var hex = "0x" + Number(C.chainId).toString(16);
		return provider.request({ method: "eth_chainId" }).then(function (id) {
			if (parseInt(id, 16) === Number(C.chainId)) return;
			return provider.request({ method: "wallet_switchEthereumChain", params: [{ chainId: hex }] }).catch(function (e) {
				if (e && e.code === 4902) {
					return provider.request({ method: "wallet_addEthereumChain", params: [{ chainId: hex, chainName: C.chainName, nativeCurrency: { name: "BNB", symbol: C.currency, decimals: 18 }, rpcUrls: [C.rpc], blockExplorerUrls: [C.explorer] }] });
				}
				throw e;
			});
		});
	}

	function connect() {
		if (!provider) { msg("No browser wallet found. Install MetaMask and reload this page.", "error"); return Promise.reject(); }
		return provider.request({ method: "eth_requestAccounts" }).then(function (a) {
			account = a[0];
			return ensureChain();
		}).then(function () {
			return provider.request({ method: "eth_call", params: [{ to: C.presale, data: SEL.owner }, "latest"] });
		}).then(function (o) {
			owner = "0x" + o.slice(-40);
			var isOwner = owner.toLowerCase() === account.toLowerCase();
			q("who").innerHTML = "Connected: <code>" + account + "</code> " + (isOwner ? '<strong class="nsc-ok">✓ owner</strong>' : '<strong class="nsc-err">✗ not the owner — switch accounts in MetaMask</strong>');
			q("connect").textContent = "Reconnect";
			return isOwner;
		}).catch(function (e) { if (e) msg(nice(e), "error"); });
	}

	function nice(e) {
		if (!e) return "Something went wrong.";
		if (e.code === 4001) return "Cancelled in the wallet.";
		var t = JSON.stringify(e) + " " + (e.message || "");
		for (var k in ERR) if (t.indexOf(k) !== -1) return ERR[k];
		return (e.message || "Something went wrong.").slice(0, 220);
	}

	function send(label, data) {
		var go = account ? Promise.resolve(owner && owner.toLowerCase() === account.toLowerCase()) : connect();
		return go.then(function (ok) {
			if (!ok) { msg("Connect the owner wallet first.", "error"); return; }
			msg(label + ": confirm in your wallet…", "info");
			return ensureChain().then(function () {
				return provider.request({ method: "eth_sendTransaction", params: [{ from: account, to: C.presale, data: data, value: "0x0" }] });
			}).then(function (hash) {
				var link = '<a href="' + C.explorer + "/tx/" + hash + '" target="_blank" rel="noopener">view on BscScan</a>';
				msg(label + ": sent, waiting for the network… (" + link + ")", "info");
				return wait(hash).then(function (r) {
					if (r.status === "0x1") {
						msg("✅ " + label + ": done (" + link + "). Refreshing figures…", "success");
						fetch(C.rest + "stats?fresh=1", { credentials: "same-origin", headers: { "X-WP-Nonce": C.nonce } }).finally(function () { setTimeout(function () { location.reload(); }, 1800); });
					} else {
						msg("❌ " + label + ": the transaction failed (" + link + ").", "error");
					}
				});
			});
		}).catch(function (e) { msg(nice(e), "error"); });
	}

	function wait(hash) {
		return new Promise(function (resolve, reject) {
			var n = 0;
			(function poll() {
				provider.request({ method: "eth_getTransactionReceipt", params: [hash] }).then(function (r) {
					if (r) return resolve(r);
					if (++n > 120) return reject(new Error("Still pending — check BscScan."));
					setTimeout(poll, 1500);
				}).catch(reject);
			})();
		});
	}

	root.addEventListener("click", function (e) {
		var b = e.target.closest("[data-a]");
		if (!b || b.tagName !== "BUTTON") return;
		var a = b.getAttribute("data-a");
		if (a === "connect") return connect();
		if (a === "open") return send("Open the sale", SEL.setSaleActive + word("1"));
		if (a === "pause") return send("Pause the sale", SEL.setSaleActive + word("0"));
		if (a === "set-price") {
			var p = units(q("price").value);
			if (!p || p <= 0n) return msg("Enter a price like 0.0042", "error");
			return send("Set price to $" + q("price").value.trim(), SEL.setPriceUsd + word(p.toString(16)));
		}
		if (a === "set-treasury") {
			var t = q("treasury").value.trim();
			if (!isAddr(t)) return msg("Enter a full wallet address (0x… 42 characters).", "error");
			if (!confirm("Send all future buyers' BNB to " + t + "?\n\nCheck you control this wallet.")) return;
			return send("Change treasury", SEL.setTreasury + word(t));
		}
		if (a === "set-limits") {
			var mn = units(q("min").value), mx = units(q("max").value || "0");
			if (mn === null || mx === null) return msg("Enter numbers like 0.001 and 0.", "error");
			return send("Save limits", SEL.setLimits + word(mn.toString(16)) + word(mx.toString(16)));
		}
		if (a === "withdraw") {
			var to = q("wd-to").value.trim(), amt = units(q("wd-amount").value);
			if (!isAddr(to) || !amt) return msg("Enter a wallet address and an amount of N$C.", "error");
			if (!confirm("Take " + q("wd-amount").value.trim() + " N$C out of the presale and send it to " + to + "?")) return;
			return send("Take N$C out of the presale", SEL.withdrawUnsold + word(to) + word(amt.toString(16)));
		}
	});

	// Live preview of what a new price means.
	var priceInput = q("price");
	if (priceInput) {
		var bnbUsd = null;
		fetch(C.rest + "stats").then(function (r) { return r.json(); }).then(function (s) { bnbUsd = s && s.bnbUsd ? Number(BigInt(s.bnbUsd) / 10n ** 12n) / 1e6 : null; });
		priceInput.addEventListener("input", function () {
			var v = parseFloat(priceInput.value);
			q("price-preview").textContent = v > 0 && bnbUsd ? "1 BNB would buy " + Math.floor(bnbUsd / v).toLocaleString() + " N$C; $100 buys " + Math.floor(100 / v).toLocaleString() + " N$C." : "";
		});
	}
})();
