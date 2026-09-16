const API_URL = process.env.API_URL ?? 'http://app:8000';
const WORKER_TOKEN = process.env.CRM_FLOW_WORKER_TOKEN ?? '';

export async function releaseExpired(job) {
  const response = await fetch(`${API_URL}/api/v1/internal/leads/release-expired`, {
    method: 'POST',
    headers: {
      Authorization: `Bearer ${WORKER_TOKEN}`,
      'Idempotency-Key': `lock-${job.id}`,
      'X-Correlation-Id': `lock-${job.id}`,
    },
  });

  if (!response.ok) {
    throw new Error(`lock expire sweep failed: ${response.status}`);
  }

  return response.json();
}
