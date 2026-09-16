import { Queue, Worker } from 'bullmq';
import IORedis from 'ioredis';
import { processLead } from './jobs/processLead.js';
import { relayOutbox } from './jobs/relayOutbox.js';
import { sweepSla } from './jobs/sweepSla.js';
import { recycleIdle } from './jobs/recycleIdle.js';
import { retryDead } from './jobs/retryDead.js';
import { releaseExpired } from './jobs/releaseExpired.js';

const connection = new IORedis({
  host: process.env.REDIS_HOST ?? '127.0.0.1',
  port: Number(process.env.REDIS_PORT ?? 6379),
  maxRetriesPerRequest: null,
});

const prefix = process.env.BULLMQ_PREFIX ?? 'bull';

const queues = {
  intake: new Queue('leads.intake', { connection, prefix }),
  outbox: new Queue('outbox.relay', { connection, prefix }),
  sla: new Queue('sla.sweep', { connection, prefix }),
  recycle: new Queue('leads.recycle', { connection, prefix }),
  dead: new Queue('dead.retry', { connection, prefix }),
  locks: new Queue('leads.release-expired', { connection, prefix }),
};

const processors = {
  'leads.intake': processLead,
  'outbox.relay': relayOutbox,
  'sla.sweep': sweepSla,
  'leads.recycle': recycleIdle,
  'dead.retry': retryDead,
  'leads.release-expired': releaseExpired,
};

const workers = Object.entries(processors).map(([name, processor]) => {
  const worker = new Worker(name, processor, {
    connection,
    prefix,
    lockDuration: 60000,
  });

  worker.on('failed', (job, err) => {
    console.error(JSON.stringify({ msg: 'job_failed', queue: name, jobId: job?.id, err: err.message }));
  });

  worker.on('completed', (job) => {
    console.log(JSON.stringify({ msg: 'job_completed', queue: name, jobId: job.id }));
  });

  return worker;
});

await queues.sla.add('sweep', {}, { repeat: { every: Number(process.env.SLA_SWEEP_INTERVAL_MS ?? 15000) }, jobId: 'sla-sweep' });
await queues.recycle.add('sweep', {}, { repeat: { every: Number(process.env.IDLE_RECYCLE_INTERVAL_MS ?? 20000) }, jobId: 'idle-recycle' });
await queues.outbox.add('relay', {}, { repeat: { every: Number(process.env.OUTBOX_RELAY_INTERVAL_MS ?? 2000) }, jobId: 'outbox-relay' });
await queues.locks.add('sweep', {}, { repeat: { every: Number(process.env.LOCK_SWEEP_INTERVAL_MS ?? 5000) }, jobId: 'lock-expire' });

const laravelPrefix = process.env.LARAVEL_REDIS_PREFIX ?? '';

async function promoteLaravelJobs(listKey, queue) {
  const key = `${laravelPrefix}${listKey}`;
  while (true) {
    const popped = await connection.blpop(key, 5);
    if (!popped) {
      continue;
    }

    const job = JSON.parse(popped[1]);
    await queue.add('job', job, {
      jobId: job.idempotencyKey,
      attempts: 8,
      backoff: { type: 'exponential', delay: 2000 },
      removeOnComplete: 1000,
    });
  }
}

promoteLaravelJobs('crmflow:jobs:leads.intake', queues.intake).catch((err) => {
  console.error(JSON.stringify({ msg: 'promote_intake_failed', err: err.message }));
});
promoteLaravelJobs('crmflow:jobs:dead', queues.dead).catch((err) => {
  console.error(JSON.stringify({ msg: 'promote_dead_failed', err: err.message }));
});

console.log(JSON.stringify({ msg: 'worker_started', queues: Object.keys(processors) }));

const shutdown = async () => {
  await Promise.all(workers.map((worker) => worker.close()));
  await Promise.all(Object.values(queues).map((queue) => queue.close()));
  await connection.quit();
  process.exit(0);
};

process.on('SIGINT', shutdown);
process.on('SIGTERM', shutdown);
