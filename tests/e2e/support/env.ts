import os from 'node:os';
import path from 'node:path';

export const e2ePort = Number(process.env.E2E_PORT ?? 3190);

// Named after the port, so the config, the workers and the teardown agree without sharing state
export const e2eDir = path.join(os.tmpdir(), `timeline-e2e-${e2ePort}`);
