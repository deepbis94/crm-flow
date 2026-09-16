const API_URL = process.env.API_URL ?? 'http://app:8000';
const WORKER_TOKEN = process.env.CRM_FLOW_WORKER_TOKEN ?? '';

export async function recycleIdle(job) {
  const response = await fetch(`${API_URL}/api/v1/internal/leads/recycle`, {
    method: 'POST',
    headers: {
      Authorization: `Bearer ${WORKER_TOKEN}`,
      'Idempotency-Key': `recycle-${job.id}`,
      'X-Correlation-Id': `recycle-${job.id}`,
    },
  });

  if (!response.ok) {
    throw new Error(`idle recycle failed: ${response.status}`);
  }

  return response.json();
}
