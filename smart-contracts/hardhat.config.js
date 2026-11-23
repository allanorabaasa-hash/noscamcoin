require("@nomicfoundation/hardhat-toolbox");
const { subtask } = require("hardhat/config");
const { TASK_COMPILE_SOLIDITY_GET_SOLC_BUILD } = require("hardhat/builtin-tasks/task-names");

// Offline fallback: use the npm "solc" package if the compiler can't be downloaded.
subtask(TASK_COMPILE_SOLIDITY_GET_SOLC_BUILD, async (args, hre, runSuper) => {
  if (process.env.USE_NPM_SOLC) {
    return { compilerPath: require.resolve("solc/soljson.js"), isSolcJs: true,
             version: args.solcVersion, longVersion: require("solc").version() };
  }
  return runSuper();
});

module.exports = {
  solidity: { version: "0.8.24", settings: { optimizer: { enabled: true, runs: 200 }, evmVersion: "cancun" } },
  networks: {
    bscTestnet: { url: "https://data-seed-prebsc-1-s1.bnbchain.org:8545", chainId: 97,
                  accounts: process.env.PRIVATE_KEY ? [process.env.PRIVATE_KEY] : [] },
    bsc:        { url: "https://bsc-dataseed.bnbchain.org", chainId: 56,
                  accounts: process.env.PRIVATE_KEY ? [process.env.PRIVATE_KEY] : [] },
  },
};
