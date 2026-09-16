const API_URL = process.env.API_URL ?? 'http://app:8000';
const WORKER_TOKEN = process.env.CRM_FLOW_WORKER_TOKEN ?? '';

export async function relayOutbox(job) {
  const response = await fetch(`${API_URL}/api/v1/internal/outbox/relay`, {
    method: 'POST',
    headers: {
      Authorization: `Bearer ${WORKER_TOKEN}`,
      'Idempotency-Key': `outbox-${job.id}`,
      'X-Correlation-Id': `outbox-${job.id}`,
    },
  });

  if (!response.ok) {
    throw new Error(`outbox relay failed: ${response.status}`);
  }

  return response.json();
}
