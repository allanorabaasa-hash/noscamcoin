const { expect } = require("chai");
const { ethers } = require("hardhat");

describe("NSCPresale (USD pricing)", function () {
  let token, presale, feed, owner, victim, liq, treasury, buyer, buyer2;
  const E = (n) => ethers.parseEther(n);
  const BNB_USD = 600n * 10n ** 8n;   // Chainlink format: $600.00000000
  const PRICE = E("0.004");          // $0.004 per NSC -> 1 BNB ($600) buys 150,000 NSC

  beforeEach(async () => {
    [owner, victim, liq, treasury, buyer, buyer2] = await ethers.getSigners();
    token = await ethers.deployContract("NoScamCoin", [victim.address, liq.address]);
    feed = await ethers.deployContract("MockPriceFeed", [BNB_USD]);
    presale = await ethers.deployContract("NSCPresale", [
      await token.getAddress(), treasury.address, await feed.getAddress(), PRICE,
    ]);
    const p = await presale.getAddress();
    await token.setTaxExempt(p, true);
    await token.transfer(p, E("400000000"));
    await presale.setSaleActive(true);
  });

  it("quotes from the $ price and live BNB rate; buyer gets exactly the quote", async () => {
    expect(await presale.bnbUsd()).to.equal(E("600"));
    expect(await presale.quote(E("1"))).to.equal(E("150000"));
    const before = await ethers.provider.getBalance(treasury.address);
    await presale.connect(buyer).buy({ value: E("0.5") });
    expect(await token.balanceOf(buyer.address)).to.equal(E("75000"));
    expect((await ethers.provider.getBalance(treasury.address)) - before).to.equal(E("0.5"));
    expect(await ethers.provider.getBalance(await presale.getAddress())).to.equal(0n);
  });

  it("keeps the $ price fixed when BNB moves", async () => {
    await feed.set(300n * 10n ** 8n); // BNB halves
    await presale.connect(buyer).buy({ value: E("1") });
    expect(await token.balanceOf(buyer.address)).to.equal(E("75000")); // $300 / $0.004
  });

  it("owner $ price change applies to the next buy", async () => {
    await presale.setPriceUsd(E("0.006"));
    await presale.connect(buyer).buy({ value: E("0.3") }); // $180 / $0.006
    expect(await token.balanceOf(buyer.address)).to.equal(E("30000"));
  });

  it("refuses to sell on a stale or broken oracle price", async () => {
    const now = (await ethers.provider.getBlock("latest")).timestamp;
    await feed.setUpdatedAt(now - 3 * 3600);
    await expect(presale.connect(buyer).buy({ value: E("1") })).to.be.revertedWithCustomError(presale, "BadOraclePrice");
    await feed.set(0n);
    await expect(presale.connect(buyer).buy({ value: E("1") })).to.be.revertedWithCustomError(presale, "BadOraclePrice");
  });

  it("sending BNB directly also buys", async () => {
    await buyer.sendTransaction({ to: await presale.getAddress(), value: E("0.01") });
    expect(await token.balanceOf(buyer.address)).to.equal(E("1500"));
  });

  it("blocks buys when paused, too small, over wallet limit, or sold out", async () => {
    await presale.setSaleActive(false);
    await expect(presale.connect(buyer).buy({ value: E("1") })).to.be.revertedWithCustomError(presale, "SaleNotActive");
    await presale.setSaleActive(true);
    await expect(presale.connect(buyer).buy({ value: E("0.0001") })).to.be.revertedWithCustomError(presale, "BelowMinimum");
    await presale.setLimits(E("0.001"), E("1"));
    await presale.connect(buyer).buy({ value: E("0.6") });
    await expect(presale.connect(buyer).buy({ value: E("0.6") })).to.be.revertedWithCustomError(presale, "AboveWalletLimit");
    await presale.setLimits(E("0.001"), 0n);
    await expect(presale.connect(buyer2).buy({ value: E("5000") })).to.be.revertedWithCustomError(presale, "NotEnoughTokensLeft");
  });

  it("cannot renounce ownership by accident", async () => {
    await expect(presale.renounceOwnership()).to.be.revertedWithCustomError(presale, "RenounceDisabled");
    expect(await presale.owner()).to.equal(owner.address);
  });

  it("only owner controls settings and can withdraw unsold NSC", async () => {
    await expect(presale.connect(buyer).setPriceUsd(1n)).to.be.reverted;
    await presale.withdrawUnsold(owner.address, E("400000000"));
    expect(await presale.tokensLeft()).to.equal(0n);
  });
});
