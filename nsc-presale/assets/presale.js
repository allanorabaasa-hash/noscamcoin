/**
 * NOSCAM presale: live figures and the Buy N$C window.
 *
 * Wallets: every browser-extension / in-app wallet that announces itself (EIP-6963:
 * MetaMask, Trust Wallet, Binance Wallet, OKX, Coinbase, Rabby…), the classic
 * window.ethereum, phone deep links into MetaMask / Trust Wallet, and WalletConnect
 * (QR code, any wallet) when a project ID is set in NSC Presale → Settings.
 *
 * Buying calls the presale contract's buy() with the BNB amount as the value; the
 * contract sends N$C to the buyer in the same transaction.
 */
(function () {
	"use strict";

	var CFG = window.NSC_CFG || {};
	var SEL_BUY = "0xa6f2ae3a";
	var SEL_SPENT = "0x862c4508";
	var TRANSFER = "0xddf252ad1be2c89b69c2b068fc378daa952ba7f163c4a11628f55a4df523b3ef";
	var ERRORS = {
		"0xb7b24097": "The presale is paused right now.",
		"0x860b82a9": "That's below the smallest purchase.",
		"0x9b838ba9": "That's more than one wallet may buy in total.",
		"0x76592c6f": "Not enough N$C left in the presale for that amount.",
		"0x20c98f2c": "The BNB price feed is updating. Please try again in a minute.",
		"0x90b8ec18": "The payment couldn't be forwarded. Please try again."
	};
	var ONE = 10n ** 18n;
	var GAS_RESERVE = 5n * 10n ** 14n; // 0.0005 BNB kept for the network fee.

	var stats = null;
	var wallets = {}; // uuid => { info, provider }
	var provider = null;
	var account = null;
	var lastTx = null;

	var $ = function (sel, root) { return (root || document).querySelector(sel); };
	var $$ = function (sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); };

	/* ----------------------------------------------------------- numbers */

	function big(v) {
		try { return BigInt(v || 0); } catch (e) { return 0n; }
	}
	function parseUnits(str) {
		str = String(str || "").trim().replace(/,/g, ".");
		if (!/^\d*\.?\d*$/.test(str) || str === "" || str === ".") return null;
		var p = str.split(".");
		var frac = ((p[1] || "") + "000000000000000000").slice(0, 18);
		return BigInt(p[0] || "0") * ONE + BigInt(frac);
	}
	function formatUnits(v, dp) {
		v = big(v);
		var neg = v < 0n; if (neg) v = -v;
		var whole = v / ONE, frac = (v % ONE).toString().padStart(18, "0").slice(0, dp === undefined ? 4 : dp).replace(/0+$/, "");
		return (neg ? "-" : "") + whole.toLocaleString("en-US") + (frac ? "." + frac : "");
	}
	function toNum(v) { return Number(formatUnits(v, 6).replace(/,/g, "")); }
	function usd(n) {
		if (!isFinite(n)) return "—";
		return "$" + n.toLocaleString("en-US", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
	}
	function compact(n) {
		var u = [[1e9, "B"], [1e6, "M"], [1e3, "K"]];
		for (var i = 0; i < u.length; i++) if (n >= u[i][0]) return trim(n / u[i][0]) + u[i][1];
		return trim(n);
		function trim(x) { return x.toLocaleString("en-US", { maximumFractionDigits: 2 }); }
	}
	function quote(wei) {
		if (!stats || !big(stats.priceUsd)) return 0n;
		return (wei * big(stats.bnbUsd)) / big(stats.priceUsd);
	}

	/* ----------------------------------------------------------- live figures */

	function paint() {
		if (!stats || !stats.view) return;
		var v = stats.view;
		var map = {
			price: v.price, bnbPrice: v.bnbPrice, raisedUsd: v.raisedUsd, raisedBnb: v.raisedBnb,
			sold: v.sold, left: v.left, burned: v.burned, supply: v.supply, nscPerBnb: v.nscPerBnb, minBuy: v.minBuy,
			soldPct: (Math.round(v.soldPct * 100) / 100) + "%",
			holders: Number(stats.holders || 0).toLocaleString("en-US"),
			buys: Number(stats.buys || 0).toLocaleString("en-US"),
			saleBadge: stats.saleActive ? "PRESALE LIVE" : "PRESALE PAUSED",
			stageLabel: stats.saleActive ? "⚡ PRESALE — LIVE NOW" : "⏸ PRESALE PAUSED",
			stageShort: stats.saleActive ? "Presale live" : "Presale paused"
		};
		$$("[data-nsc]").forEach(function (el) {
			var k = el.getAttribute("data-nsc");
			if (map[k] !== undefined && el.textContent !== map[k]) el.textContent = map[k];
		});
		$$("[data-nsc-width]").forEach(function (el) {
			el.style.width = Math.max(0.6, Math.min(100, v.soldPct || 0)) + "%";
		});
		document.documentElement.classList.toggle("nsc-sale-live", !!stats.saleActive);
		updateQuote();
	}

	function loadStats() {
		return fetch(CFG.rest + "stats", { credentials: "same-origin" })
			.then(function (r) { return r.json(); })
			.then(function (d) { if (d && d.view) { stats = d; paint(); } return d; })
			.catch(function () { return stats; });
	}

	function loadRecent() {
		var box = $("[data-nsc-recent]");
		if (!box) return;
		fetch(CFG.rest + "buys?limit=8").then(function (r) { return r.json(); }).then(function (rows) {
			if (!Array.isArray(rows) || !rows.length) {
				box.innerHTML = '<p class="nsc-recent-empty">No purchases yet — be the first.</p>';
				return;
			}
			box.innerHTML = rows.map(function (r) {
				var ago = r.time ? timeAgo(r.time) : "";
				return '<a class="nsc-recent-row" href="' + r.tx + '" target="_blank" rel="noopener"><span>' + esc(r.buyer) + '</span><strong>' + esc(r.nsc) + ' N$C</strong><em>' + esc(r.bnb) + " " + esc(CFG.currency) + "</em><small>" + ago + "</small></a>";
			}).join("");
		}).catch(function () {});
	}
	function timeAgo(t) {
		var s = Math.max(1, Math.floor(Date.now() / 1000 - t));
		if (s < 60) return s + "s ago"; if (s < 3600) return Math.floor(s / 60) + "m ago";
		if (s < 86400) return Math.floor(s / 3600) + "h ago"; return Math.floor(s / 86400) + "d ago";
	}
	function esc(s) { return String(s == null ? "" : s).replace(/[&<>"']/g, function (c) { return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]; }); }

	/* ----------------------------------------------------------- wallets */

	window.addEventListener("eip6963:announceProvider", function (e) {
		var d = e.detail;
		if (!d || !d.info || !d.provider) return;
		wallets[d.info.uuid || d.info.rdns || d.info.name] = d;
		renderWallets();
	});
	function discover() {
		window.dispatchEvent(new Event("eip6963:requestProvider"));
	}

	function legacyName(p) {
		if (!p) return "Browser wallet";
		if (p.isTrust || p.isTrustWallet) return "Trust Wallet";
		if (p.isBinance || p.isBinanceChain) return "Binance Wallet";
		if (p.isOkxWallet || p.isOKExWallet) return "OKX Wallet";
		if (p.isCoinbaseWallet) return "Coinbase Wallet";
		if (p.isTokenPocket) return "TokenPocket";
		if (p.isSafePal) return "SafePal";
		if (p.isMetaMask) return "MetaMask";
		return "Browser wallet";
	}
	var isPhone = /Android|iPhone|iPad|iPod/i.test(navigator.userAgent);

	function renderWallets() {
		var box = $("[data-nsc-wallets]");
		if (!box) return;
		var list = Object.keys(wallets).map(function (k) { return wallets[k]; });
		var html = list.map(function (w, i) {
			return '<button type="button" class="nsc-wallet" data-wallet="' + i + '"><img src="' + esc(w.info.icon) + '" alt="" width="28" height="28"><span>' + esc(w.info.name) + "</span><em>Detected</em></button>";
		}).join("");
		if (!list.length && window.ethereum) {
			html += '<button type="button" class="nsc-wallet" data-wallet="legacy"><span class="nsc-wicon">🦊</span><span>' + esc(legacyName(window.ethereum)) + "</span><em>Detected</em></button>";
		}
		if (CFG.walletconnect) {
			html += '<button type="button" class="nsc-wallet" data-wallet="wc"><span class="nsc-wicon nsc-wc">◉</span><span>WalletConnect</span><em>Scan with any wallet</em></button>';
		}
		if (!list.length && !window.ethereum && !CFG.walletconnect) {
			html += '<p class="nsc-small">No wallet found in this browser. Install <a href="https://metamask.io/download/" target="_blank" rel="noopener">MetaMask</a> or <a href="https://trustwallet.com/download" target="_blank" rel="noopener">Trust Wallet</a>, then reload this page.</p>';
		}
		box.innerHTML = html;
		box._list = list;
		var mob = $("[data-nsc-mobile]");
		if (mob) mob.hidden = !(isPhone && !list.length && !window.ethereum);
	}

	function deepLinks() {
		var url = location.href.split("#")[0];
		var noProto = url.replace(/^https?:\/\//, "");
		$$("[data-deeplink]").forEach(function (a) {
			var k = a.getAttribute("data-deeplink");
			if (k === "metamask") a.href = "https://metamask.app.link/dapp/" + noProto;
			if (k === "trust") a.href = "https://link.trustwallet.com/open_url?coin_id=20000714&url=" + encodeURIComponent(url);
			if (k === "binance") {
				a.href = "#";
				a.textContent = "Copy link for Binance app";
				a.addEventListener("click", function (e) { e.preventDefault(); copy(url, a, "Link copied — paste it in the Binance app's Web3 browser"); });
			}
		});
	}

	function useProvider(p) {
		if (provider && provider.removeListener) {
			try { provider.removeListener("accountsChanged", onAccounts); provider.removeListener("chainChanged", onChain); } catch (e) {}
		}
		provider = p;
		if (p.on) { p.on("accountsChanged", onAccounts); p.on("chainChanged", onChain); }
	}
	function onAccounts(a) { account = a && a[0] ? a[0] : null; if (!account) step("wallet"); else showAccount(); }
	function onChain() { refreshBalance(); }

	function connect(which) {
		var box = $("[data-nsc-wallets]");
		var p;
		error("");
		if (which === "wc") return connectWC();
		if (which === "legacy") p = window.ethereum;
		else p = box && box._list && box._list[+which] ? box._list[+which].provider : null;
		if (!p) return;
		useProvider(p);
		return p.request({ method: "eth_requestAccounts" }).then(function (a) {
			account = a[0];
			return ensureChain();
		}).then(showAccount).catch(function (e) { step("wallet"); toast(niceError(e)); });
	}

	function connectWC() {
		var btn = $('[data-wallet="wc"] em');
		if (btn) btn.textContent = "Loading…";
		return import("https://esm.sh/@walletconnect/ethereum-provider@2").then(function (m) {
			var EP = m.EthereumProvider || (m.default && m.default.EthereumProvider) || m.default;
			var rpcMap = {}; rpcMap[CFG.chainId] = CFG.rpc;
			return EP.init({
				projectId: CFG.walletconnect,
				chains: [CFG.chainId],
				optionalChains: [CFG.chainId],
				rpcMap: rpcMap,
				showQrModal: true,
				metadata: { name: CFG.siteName || "NOSCAM Coin", description: "Buy N$C", url: CFG.siteUrl, icons: [CFG.icon] }
			});
		}).then(function (wc) {
			useProvider(wc);
			return wc.enable ? wc.enable() : wc.connect();
		}).then(function (a) {
			account = (a && a[0]) || (provider.accounts && provider.accounts[0]);
			return ensureChain();
		}).then(showAccount).catch(function (e) {
			if (btn) btn.textContent = "Scan with any wallet";
			toast(niceError(e));
		});
	}

	function hexChain() { return "0x" + Number(CFG.chainId).toString(16); }
	function ensureChain() {
		return provider.request({ method: "eth_chainId" }).then(function (id) {
			if (parseInt(id, 16) === Number(CFG.chainId)) return;
			return provider.request({ method: "wallet_switchEthereumChain", params: [{ chainId: hexChain() }] }).catch(function (e) {
				if (e && (e.code === 4902 || /Unrecognized|not added|unknown chain/i.test(e.message || ""))) {
					return provider.request({
						method: "wallet_addEthereumChain",
						params: [{
							chainId: hexChain(), chainName: CFG.chainName,
							nativeCurrency: { name: "BNB", symbol: CFG.currency, decimals: 18 },
							rpcUrls: [CFG.rpc], blockExplorerUrls: [CFG.explorer]
						}]
					});
				}
				throw e;
			});
		});
	}

	function showAccount() {
		if (!account) return;
		$("[data-nsc-account]").textContent = account.slice(0, 6) + "…" + account.slice(-4);
		step("amount");
		refreshBalance();
		updateQuote();
		var inp = $("[data-nsc-amount]");
		if (inp && !isPhone) inp.focus();
	}

	var balanceWei = null;
	var spentWei = 0n;
	function refreshBalance() {
		if (!provider || !account) return;
		provider.request({ method: "eth_getBalance", params: [account, "latest"] }).then(function (b) {
			balanceWei = big(b);
			$("[data-nsc-balance]").textContent = formatUnits(balanceWei, 4) + " " + CFG.currency;
			updateQuote();
		}).catch(function () {});
		var data = SEL_SPENT + account.toLowerCase().replace(/^0x/, "").padStart(64, "0");
		provider.request({ method: "eth_call", params: [{ to: CFG.presale, data: data }, "latest"] }).then(function (r) {
			spentWei = big(r); updateQuote();
		}).catch(function () {});
	}

	/* ----------------------------------------------------------- buy window */

	function step(name) {
		$$("#nsc-buy [data-step]").forEach(function (s) { s.hidden = s.getAttribute("data-step") !== name; });
	}
	function open() {
		var m = $("#nsc-buy");
		if (!m) return;
		m.hidden = false; m.setAttribute("aria-hidden", "false");
		document.documentElement.classList.add("nsc-modal-open");
		discover(); renderWallets();
		if (account && provider) showAccount(); else step("wallet");
		loadStats();
		setTimeout(function () { var x = $("#nsc-buy .nsc-x"); if (x && !account) x.focus(); }, 30);
	}
	function close() {
		var m = $("#nsc-buy");
		if (!m) return;
		m.hidden = true; m.setAttribute("aria-hidden", "true");
		document.documentElement.classList.remove("nsc-modal-open");
	}
	function error(msg) {
		var el = $("[data-nsc-error]");
		if (!el) return;
		el.textContent = msg || ""; el.hidden = !msg;
	}
	function toast(msg) { if (msg) { step(account ? "amount" : "wallet"); if (account) error(msg); else alert(msg); } }

	function rules() {
		if (!stats) return "";
		var min = big(stats.minBuyWei), max = big(stats.maxPerWalletWei);
		var r = "Minimum " + formatUnits(min, 4) + " " + CFG.currency;
		if (max > 0n) r += " · up to " + formatUnits(max, 4) + " " + CFG.currency + " per wallet";
		return r + ".";
	}

	function updateQuote() {
		var inp = $("[data-nsc-amount]");
		if (!inp) return;
		var wei = parseUnits(inp.value) || 0n;
		var got = quote(wei);
		var bnbUsd = stats ? toNum(stats.bnbUsd) : 0;
		$("[data-nsc-receive]").textContent = compactOrFull(got) + " N$C";
		$("[data-nsc-usd]").textContent = "≈ " + usd(toNum(wei) * bnbUsd);
		$("[data-nsc-rules]").textContent = rules();
		var btn = $("[data-nsc-confirm]");
		var problem = validate(wei, got);
		btn.disabled = !!problem || wei === 0n;
		btn.textContent = problem && wei > 0n ? problem : (wei > 0n ? "Buy " + compactOrFull(got) + " N$C" : "Enter an amount");
	}
	function compactOrFull(v) {
		var n = toNum(v);
		return n >= 1e6 ? compact(n) : n.toLocaleString("en-US", { maximumFractionDigits: 2 });
	}
	function validate(wei, got) {
		if (!stats || !stats.ok) return "Loading the price…";
		if (!stats.saleActive) return "Presale paused";
		if (!big(stats.bnbUsd)) return "Price feed updating — try shortly";
		if (wei === 0n) return "";
		if (wei < big(stats.minBuyWei)) return "Minimum is " + formatUnits(stats.minBuyWei, 4) + " " + CFG.currency;
		var max = big(stats.maxPerWalletWei);
		if (max > 0n && spentWei + wei > max) return "Over your wallet limit";
		if (got > big(stats.tokensLeft)) return "Not enough N$C left";
		if (balanceWei !== null && wei + GAS_RESERVE > balanceWei) return "Not enough " + CFG.currency + " (keep some for the fee)";
		return "";
	}

	function niceError(e) {
		if (!e) return "Something went wrong.";
		if (e.code === 4001 || /reject|denied|cancel/i.test(e.message || "")) return "You cancelled in your wallet.";
		var txt = JSON.stringify(e) + " " + (e.message || "");
		for (var sel in ERRORS) if (txt.indexOf(sel) !== -1) return ERRORS[sel];
		if (/insufficient funds/i.test(txt)) return "Not enough " + CFG.currency + " to cover the purchase and the network fee.";
		return (e.message || "Something went wrong.").slice(0, 180);
	}

	function buy() {
		var inp = $("[data-nsc-amount]");
		var wei = parseUnits(inp.value) || 0n;
		var got = quote(wei);
		var problem = validate(wei, got);
		if (problem || !wei) { error(problem); return; }
		error("");
		step("pending");
		$("[data-nsc-pending-text]").textContent = "Confirm the purchase in your wallet…";
		$("[data-nsc-pending-link]").textContent = "";
		ensureChain().then(function () {
			return provider.request({
				method: "eth_sendTransaction",
				params: [{ from: account, to: CFG.presale, value: "0x" + wei.toString(16), data: SEL_BUY }]
			});
		}).then(function (hash) {
			lastTx = hash;
			$("[data-nsc-pending-text]").textContent = "Buying… this takes a few seconds.";
			$("[data-nsc-pending-link]").innerHTML = '<a href="' + CFG.explorer + "/tx/" + hash + '" target="_blank" rel="noopener">Follow it on BscScan ↗</a>';
			return waitReceipt(hash);
		}).then(function (rcpt) {
			if (!rcpt || rcpt.status !== "0x1") throw new Error("The purchase didn't go through. No N$C was bought and only the network fee was spent.");
			var amount = got;
			(rcpt.logs || []).forEach(function (l) {
				if (l.topics && l.topics[0] === TRANSFER && l.address.toLowerCase() === CFG.token.toLowerCase() && l.topics[2] && l.topics[2].slice(-40).toLowerCase() === account.slice(2).toLowerCase()) amount = big(l.data);
			});
			$("[data-nsc-bought]").textContent = toNum(amount).toLocaleString("en-US", { maximumFractionDigits: 2 }) + " N$C";
			$("[data-nsc-txlink]").href = CFG.explorer + "/tx/" + lastTx;
			step("done");
			inp.value = "";
			refreshBalance();
			setTimeout(function () { loadStats(); loadRecent(); }, 2500);
		}).catch(function (e) {
			step("amount");
			error(niceError(e));
		});
	}

	function waitReceipt(hash) {
		var tries = 0;
		return new Promise(function (resolve, reject) {
			(function poll() {
				provider.request({ method: "eth_getTransactionReceipt", params: [hash] }).then(function (r) {
					if (r) return resolve(r);
					if (++tries > 120) return reject(new Error("Still waiting for the network. Check the transaction on BscScan."));
					setTimeout(poll, 1500);
				}).catch(function () {
					if (++tries > 120) return reject(new Error("Lost contact with the wallet. Check the transaction on BscScan."));
					setTimeout(poll, 2000);
				});
			})();
		});
	}

	function watchAsset() {
		if (!provider) return;
		provider.request({
			method: "wallet_watchAsset",
			params: { type: "ERC20", options: { address: CFG.token, symbol: "NSC", decimals: 18, image: CFG.icon } }
		}).catch(function () {});
	}

	function copy(text, el, done) {
		var ok = function () { if (el) { var t = el.textContent; el.textContent = done || "Copied"; setTimeout(function () { el.textContent = t; }, 1800); } };
		if (navigator.clipboard) navigator.clipboard.writeText(text).then(ok, ok); else ok();
	}

	/* ----------------------------------------------------------- wiring */

	document.addEventListener("click", function (e) {
		var t = e.target.closest ? e.target : e.target.parentElement;
		if (!t) return;
		var buyBtn = t.closest("[data-nsc-buy]");
		var link = t.closest('a[href="#buy"]');
		if (buyBtn || (link && document.getElementById("nsc-buy"))) { e.preventDefault(); open(); return; }
		if (t.closest("[data-nsc-close]")) { close(); return; }
		var w = t.closest("[data-wallet]");
		if (w) { connect(w.getAttribute("data-wallet")); return; }
		if (t.closest("[data-nsc-confirm]")) { buy(); return; }
		if (t.closest("[data-nsc-max]")) {
			if (balanceWei !== null) {
				var m = balanceWei > GAS_RESERVE ? balanceWei - GAS_RESERVE : 0n;
				$("[data-nsc-amount]").value = formatUnits(m, 6).replace(/,/g, "");
				updateQuote();
			}
			return;
		}
		var q = t.closest(".nsc-quick [data-q]");
		if (q) { $("[data-nsc-amount]").value = q.getAttribute("data-q"); updateQuote(); return; }
		if (t.closest("[data-nsc-disconnect]")) { account = null; provider = null; step("wallet"); renderWallets(); return; }
		if (t.closest("[data-nsc-watch]")) { watchAsset(); return; }
		if (t.closest("[data-nsc-again]")) { step("amount"); return; }
		var c = t.closest("[data-copy]");
		if (c) { copy(c.getAttribute("data-copy"), c); }
	});
	document.addEventListener("input", function (e) {
		if (e.target && e.target.matches && e.target.matches("[data-nsc-amount]")) { error(""); updateQuote(); }
	});
	document.addEventListener("keydown", function (e) {
		var m = $("#nsc-buy");
		if (e.key === "Escape" && m && !m.hidden) close();
	});

	function boot() {
		deepLinks();
		discover();
		loadStats();
		loadRecent();
		setInterval(function () { if (!document.hidden) loadStats(); }, 30000);
		setInterval(function () { if (!document.hidden) loadRecent(); }, 60000);
		if (location.hash === "#buy-now") open();
	}
	if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", boot); else boot();
})();
