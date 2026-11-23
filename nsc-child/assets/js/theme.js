/* NOSCAM theme: animated background, particles, reveal-on-scroll, menu and FAQ. */
(function () {
	"use strict";
	var reduce = window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches;

	// Background network.
	var canvas = document.getElementById("bg-canvas");
	if (canvas && canvas.getContext && !reduce) {
		var ctx = canvas.getContext("2d"), W, H, nodes = [];
		var count = window.innerWidth < 700 ? 36 : 80;
		var resize = function () { W = canvas.width = window.innerWidth; H = canvas.height = window.innerHeight; };
		resize();
		window.addEventListener("resize", resize);
		for (var i = 0; i < count; i++) {
			nodes.push({ x: Math.random() * W, y: Math.random() * H, vx: (Math.random() - 0.5) * 0.4, vy: (Math.random() - 0.5) * 0.4, r: Math.random() * 2 + 1, c: Math.random() > 0.5 ? "124,58,237" : "6,182,212" });
		}
		(function loop() {
			if (!document.hidden) {
				ctx.clearRect(0, 0, W, H);
				for (var a = 0; a < nodes.length; a++) {
					var n = nodes[a];
					n.x += n.vx; n.y += n.vy;
					if (n.x < 0 || n.x > W) n.vx *= -1;
					if (n.y < 0 || n.y > H) n.vy *= -1;
					ctx.beginPath(); ctx.arc(n.x, n.y, n.r, 0, Math.PI * 2); ctx.fillStyle = "rgba(" + n.c + ",.75)"; ctx.fill();
					for (var b = a + 1; b < nodes.length; b++) {
						var dx = n.x - nodes[b].x, dy = n.y - nodes[b].y, d = Math.sqrt(dx * dx + dy * dy);
						if (d < 140) { ctx.beginPath(); ctx.moveTo(n.x, n.y); ctx.lineTo(nodes[b].x, nodes[b].y); ctx.strokeStyle = "rgba(124,58,237," + (1 - d / 140) * 0.22 + ")"; ctx.lineWidth = 0.5; ctx.stroke(); }
					}
				}
			}
			requestAnimationFrame(loop);
		})();
	}

	// Floating particles in the hero.
	var pc = document.getElementById("particles");
	if (pc && !reduce) {
		var cols = ["rgba(124,58,237,.7)", "rgba(6,182,212,.6)", "rgba(157,92,246,.5)", "rgba(192,132,252,.4)", "rgba(34,211,238,.4)"];
		for (var p = 0; p < 22; p++) {
			var el = document.createElement("div"), s = Math.random() * 5 + 2;
			el.className = "particle";
			el.style.cssText = "width:" + s + "px;height:" + s + "px;background:" + cols[Math.floor(Math.random() * cols.length)] + ";left:" + Math.random() * 100 + "%;animation-duration:" + (Math.random() * 14 + 8) + "s;animation-delay:-" + Math.random() * 14 + "s";
			pc.appendChild(el);
		}
	}

	// Reveal on scroll.
	if ("IntersectionObserver" in window) {
		var io = new IntersectionObserver(function (entries) {
			entries.forEach(function (x) { if (x.isIntersecting) { x.target.classList.add("visible"); io.unobserve(x.target); } });
		}, { threshold: 0.12 });
		document.querySelectorAll(".fade-up").forEach(function (el) { io.observe(el); });
	} else {
		document.querySelectorAll(".fade-up").forEach(function (el) { el.classList.add("visible"); });
	}

	// Smooth in-page links (account for the fixed header).
	document.addEventListener("click", function (e) {
		var a = e.target.closest && e.target.closest('a[href^="#"], a[href*="/#"]');
		if (!a) return;
		var href = a.getAttribute("href"), hash = href.slice(href.indexOf("#"));
		if (hash.length < 2 || hash === "#buy") return;
		var path = href.split("#")[0];
		if (path && path.replace(/\/$/, "") !== location.href.split("#")[0].replace(/\/$/, "")) return;
		var t = document.querySelector(hash);
		if (!t) return;
		e.preventDefault();
		t.scrollIntoView({ behavior: reduce ? "auto" : "smooth", block: "start" });
		history.replaceState(null, "", hash);
	});

	// Mobile menu.
	var hb = document.getElementById("hamburger"), mm = document.getElementById("mobileMenu");
	if (hb && mm) {
		var set = function (open) {
			mm.classList.toggle("open", open); hb.classList.toggle("open", open);
			hb.setAttribute("aria-expanded", open ? "true" : "false");
			document.body.style.overflow = open ? "hidden" : "";
		};
		hb.addEventListener("click", function () { set(!mm.classList.contains("open")); });
		mm.addEventListener("click", function (e) { if (e.target.closest("a, button")) set(false); });
	}

	// FAQ.
	window.toggleFaq = function (btn) {
		var item = btn.parentElement;
		document.querySelectorAll(".faq-item.open").forEach(function (i) { if (i !== item) { i.classList.remove("open"); i.querySelector(".faq-q").setAttribute("aria-expanded", "false"); } });
		item.classList.toggle("open");
		btn.setAttribute("aria-expanded", item.classList.contains("open") ? "true" : "false");
	};
	document.querySelectorAll(".faq-q").forEach(function (b) {
		b.setAttribute("type", "button"); b.setAttribute("aria-expanded", "false");
		b.addEventListener("click", function () { window.toggleFaq(b); });
	});
})();
