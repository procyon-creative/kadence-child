const { spawnSync } = require('node:child_process');
const path = require('node:path');

class EnvironmentStopper {
  constructor(run = spawnSync) {
    this.run = run;
    const manifest = require('@wordpress/env/package.json');
    this.cli = path.join(path.dirname(require.resolve('@wordpress/env/package.json')), manifest.bin['wp-env']);
    this.cwd = path.resolve(__dirname, '..');
  }

  stop() {
    let failed = false;
    for (const config of ['.wp-env.json', '.wp-env.test.json']) {
      try {
        const result = this.run(process.execPath, [this.cli, 'status', `--config=${config}`, '--json'], {
          cwd: this.cwd,
          encoding: 'utf8',
        });
        if (result.error || result.status !== 0) {
          throw result.error || new Error(result.stderr || `Could not inspect ${config}`);
        }
        if (JSON.parse(result.stdout).status === 'uninitialized') {
          continue;
        }
        const stopped = this.run(process.execPath, [this.cli, 'stop', `--config=${config}`], {
          cwd: this.cwd,
          stdio: 'inherit',
        });
        if (stopped.error || stopped.status !== 0) {
          throw stopped.error || new Error(`Could not stop ${config}`);
        }
      } catch (error) {
        console.error(error.message);
        failed = true;
      }
    }
    return failed ? 1 : 0;
  }
}

module.exports = EnvironmentStopper;

if (require.main === module) {
  process.exitCode = new EnvironmentStopper().stop();
}
