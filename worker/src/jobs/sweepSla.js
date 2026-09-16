const API_URL = process.env.API_URL ?? 'http://app:8000';
const WORKER_TOKEN = process.env.CRM_FLOW_WORKER_TOKEN ?? '';

export async function sweepSla(job) {
  const response = await fetch(`${API_URL}/api/v1/internal/sla/sweep`, {
    method: 'POST',
    headers: {
      Authorization: `Bearer ${WORKER_TOKEN}`,
      'Idempotency-Key': `sla-${job.id}`,
      'X-Correlation-Id': `sla-${job.id}`,
    },
  });

  if (!response.ok) {
    throw new Error(`sla sweep failed: ${response.status}`);
  }

  return response.json();
}
