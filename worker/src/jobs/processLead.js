const API_URL = process.env.API_URL ?? 'http://app:8000';
const WORKER_TOKEN = process.env.CRM_FLOW_WORKER_TOKEN ?? '';

async function post(path, body = {}, idempotencyKey) {
  const response = await fetch(`${API_URL}${path}`, {
    method: 'POST',
    headers: {
      Authorization: `Bearer ${WORKER_TOKEN}`,
      'Content-Type': 'application/json',
      'Idempotency-Key': idempotencyKey,
      'X-Correlation-Id': idempotencyKey,
    },
    body: JSON.stringify(body),
  });

  if (!response.ok) {
    const text = await response.text();
    throw new Error(`${path} failed: ${response.status} ${text}`);
  }

  return response.json();
}

export async function processLead(job) {
  const payload = job.data?.payload ?? job.data ?? {};
  return post('/api/v1/internal/leads/process', payload, job.id);
}
