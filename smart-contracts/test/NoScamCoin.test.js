const { expect } = require("chai");
const { ethers } = require("hardhat");

describe("NoScamCoin", function () {
  let token, owner, victim, liq, alice, bob;
  const E = (n) => ethers.parseEther(n);

  beforeEach(async () => {
    [owner, victim, liq, alice, bob] = await ethers.getSigners();
    token = await ethers.deployContract("NoScamCoin", [victim.address, liq.address]);
  });

  it("mints 1B to the deployer, no tax for owner", async () => {
    expect(await token.totalSupply()).to.equal(E("1000000000"));
    await token.transfer(alice.address, E("1000"));
    expect(await token.balanceOf(alice.address)).to.equal(E("1000"));
  });

  it("takes 3%: 1% burn, 1% victim fund, 1% liquidity", async () => {
    await token.transfer(alice.address, E("1000"));
    const supplyBefore = await token.totalSupply();
    await token.connect(alice).transfer(bob.address, E("100"));
    expect(await token.balanceOf(bob.address)).to.equal(E("97"));
    expect(await token.balanceOf(victim.address)).to.equal(E("1"));
    expect(await token.balanceOf(liq.address)).to.equal(E("1"));
    expect(supplyBefore - (await token.totalSupply())).to.equal(E("1"));
  });

  it("can lower tax but never above 3%", async () => {
    await expect(token.setTaxes(200, 100, 1)).to.be.revertedWithCustomError(token, "TaxTooHigh");
    await token.setTaxes(0, 0, 0);
    await token.transfer(alice.address, E("10"));
    await token.connect(alice).transfer(bob.address, E("10"));
    expect(await token.balanceOf(bob.address)).to.equal(E("10"));
  });

  it("only owner can change settings; renounce locks them", async () => {
    await expect(token.connect(alice).setTaxes(0, 0, 0)).to.be.reverted;
    await token.renounceOwnership();
    await expect(token.setTaxes(0, 0, 0)).to.be.reverted;
  });
});
