import { ref } from 'vue';

/** Fetches aggregated analytics from aurora-admin/v1/analytics for a range. */
export function useAnalyticsData(serverData) {
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
      const url = new URL(`${serverData.restUrl}aurora-admin/v1/analytics`);
      url.searchParams.set('from', fmt(from));
      url.searchParams.set('to', fmt(to));
      const res = await fetch(url, { headers: { 'X-WP-Nonce': serverData.restNonce } });
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
