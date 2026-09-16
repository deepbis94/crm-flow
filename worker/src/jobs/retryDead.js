const API_URL = process.env.API_URL ?? 'http://app:8000';
const WORKER_TOKEN = process.env.CRM_FLOW_WORKER_TOKEN ?? '';

export async function retryDead(job) {
  const eventId = job.data?.payload?.event_id ?? job.data?.event_id ?? null;
  const response = await fetch(`${API_URL}/api/v1/internal/dead-letter/retry`, {
    method: 'POST',
    headers: {
      Authorization: `Bearer ${WORKER_TOKEN}`,
      'Content-Type': 'application/json',
      'Idempotency-Key': `dead-${job.id}`,
      'X-Correlation-Id': `dead-${job.id}`,
    },
    body: JSON.stringify({ event_id: eventId }),
  });

  if (!response.ok) {
    throw new Error(`dead-letter retry failed: ${response.status}`);
  }

  return response.json();
}
