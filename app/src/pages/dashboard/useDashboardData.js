import { ref } from 'vue';

/**
 * Fetches all dashboard card data from aurora-admin/v1/dashboard for a
 * given date range. Re-callable when the range changes.
 */
export function useDashboardData(serverData) {
  const data = ref(null);
  const loading = ref(false);
  const error = ref(false);

  const fmt = (d) => {
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${y}-${m}-${day}`;
  };

  const load = async (from, to) => {
    loading.value = true;
    error.value = false;
    try {
      const url = new URL(`${serverData.restUrl}aurora-admin/v1/dashboard`);
      url.searchParams.set('from', fmt(from));
      url.searchParams.set('to', fmt(to));
      const res = await fetch(url, {
        headers: { 'X-WP-Nonce': serverData.restNonce },
      });
      if (!res.ok) throw new Error('Request failed');
      data.value = await res.json();
    } catch {
      error.value = true;
    } finally {
      loading.value = false;
    }
  };

  return { data, loading, error, load };
}
