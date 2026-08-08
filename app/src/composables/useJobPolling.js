import { ref, onUnmounted } from 'vue';

// Phase weights for a rough overall-progress percentage. Backup and
// restore jobs use different phase sets (see BackupEngine::effective_phases
// on the PHP side) — only the phases relevant to whichever job is active
// contribute, everything else is ignored.
const PHASE_WEIGHTS = {
  db_dump: 40,
  file_zip: 40,
  upload_remote: 20,
  download_remote: 20,
  db_restore: 50,
  file_restore: 50,
};

const PHASE_LABELS = {
  pending: 'Starting…',
  db_dump: 'Backing up the database…',
  file_zip: 'Archiving files…',
  upload_remote: 'Uploading to remote storage…',
  download_remote: 'Downloading from remote storage…',
  db_restore: 'Restoring the database…',
  file_restore: 'Restoring files…',
  done: 'Done',
  failed: 'Failed',
  cancelled: 'Cancelled',
};

/**
 * Drives a backup/restore job to completion by repeatedly calling the
 * REST "step" route — a setTimeout-recursion loop (not setInterval), so a
 * slow step can never overlap the next poll for the same job. The
 * server-side wp_cron safety net keeps the job moving even if this stops
 * polling (e.g. the tab is closed mid-backup) — this composable is purely
 * the foreground "watch it happen live" experience, not the only thing
 * keeping a job alive.
 */
export function useJobPolling(api) {
  const job = ref(null);
  const isPolling = ref(false);
  const error = ref('');
  let timer = null;
  let stopped = false;

  const percent = () => {
    if (!job.value) return null;
    const weight = PHASE_WEIGHTS[job.value.phase];
    if (!weight) return job.value.status === 'done' ? 100 : null;
    // No fine-grained progress within a phase is cheap to compute
    // client-side (server tracks byte/row offsets, not percentages) — an
    // indeterminate bar per phase, weighted by how much of the whole job
    // that phase represents, is a reasonable approximation without a
    // second round trip just to compute a number.
    return null;
  };

  const label = () => {
    if (!job.value) return '';
    if (job.value.status === 'failed') return job.value.error_message || 'Failed';
    return PHASE_LABELS[job.value.phase] || PHASE_LABELS[job.value.status] || '';
  };

  const poll = async (jobId) => {
    if (stopped) return;
    try {
      const res = await api(`/backup/jobs/${jobId}/step`, { method: 'POST' });
      job.value = res.job;
    } catch (e) {
      error.value = e.message || 'Request failed';
      isPolling.value = false;
      return;
    }

    if (!job.value || ['done', 'failed', 'cancelled'].includes(job.value.status)) {
      isPolling.value = false;
      return;
    }

    timer = setTimeout(() => poll(jobId), 1500);
  };

  const start = (jobId) => {
    stopped = false;
    isPolling.value = true;
    error.value = '';
    poll(jobId);
  };

  const stop = () => {
    stopped = true;
    isPolling.value = false;
    if (timer) clearTimeout(timer);
  };

  onUnmounted(stop);

  return { job, isPolling, error, start, stop, percent, label };
}
