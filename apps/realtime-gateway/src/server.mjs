import { readConfig } from './config.mjs';
import { createGateway } from './gateway.mjs';

const config = readConfig();
const gateway = createGateway(config);
await gateway.start();
process.stdout.write(`Realtime Gateway ready on ${config.host}:${config.port}\n`);

let stopping = false;
async function stop() {
  if (stopping) return;
  stopping = true;
  await gateway.stop();
}
process.once('SIGTERM', () => { void stop(); });
process.once('SIGINT', () => { void stop(); });
