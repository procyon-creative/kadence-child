const assert = require('node:assert/strict');
const { test } = require('node:test');
const EnvironmentStopper = require('../scripts/stop-environments.cjs');

test('stops tests when development has never been initialized', () => {
  const stopped = [];
  const stopper = new EnvironmentStopper((command, args) => {
    const config = args.find((arg) => arg.startsWith('--config='));
    if (args[1] === 'status') {
      return { status: 0, stdout: JSON.stringify({ status: config === '--config=.wp-env.json' ? 'uninitialized' : 'running' }) };
    }
    stopped.push(config);
    return { status: 0 };
  });

  assert.equal(stopper.stop(), 0);
  assert.deepEqual(stopped, ['--config=.wp-env.test.json']);
});

test('attempts the test stop even when the development stop fails', (context) => {
  context.mock.method(console, 'error', () => {});
  const stopped = [];
  const stopper = new EnvironmentStopper((command, args) => {
    if (args[1] === 'status') {
      return { status: 0, stdout: '{"status":"running"}' };
    }
    const config = args.find((arg) => arg.startsWith('--config='));
    stopped.push(config);
    return { status: config === '--config=.wp-env.json' ? 1 : 0 };
  });

  assert.equal(stopper.stop(), 1);
  assert.deepEqual(stopped, ['--config=.wp-env.json', '--config=.wp-env.test.json']);
});

test('does not stop or initialize environments that have never been started', () => {
  let calls = 0;
  const stopper = new EnvironmentStopper((command, args) => {
    calls++;
    assert.equal(args[1], 'status');
    return { status: 0, stdout: '{"status":"uninitialized"}' };
  });

  assert.equal(stopper.stop(), 0);
  assert.equal(calls, 2);
});
