(function () {
  'use strict';

  const tbody = document.getElementById('exams-tbody');
  const errorBox = document.getElementById('exams-error');

  const questionModalEl = document.getElementById('question-modal');
  const questionModal = new bootstrap.Modal(questionModalEl);
  const questionModalExamId = document.getElementById('question-modal-exam-id');
  const questionModalBody = document.getElementById('question-modal-body');

  async function api(url, options = {}) {
    const res = await fetch(url, {
      headers: { 'Content-Type': 'application/json' },
      ...options,
    });
    let data = null;
    try {
      data = await res.json();
    } catch (e) {
      /* no body */
    }
    if (!res.ok) {
      const message = (data && data.error) || `Request failed (${res.status})`;
      throw new Error(message);
    }
    return data;
  }

  function showError(message) {
    errorBox.textContent = message;
    errorBox.classList.remove('d-none');
  }

  function clearError() {
    errorBox.classList.add('d-none');
  }

  function renderRows(exams) {
    tbody.innerHTML = '';

    if (!exams.length) {
      tbody.innerHTML = '<tr><td colspan="5" class="text-muted small">No exams found.</td></tr>';
      return;
    }

    for (const exam of exams) {
      const tr = document.createElement('tr');
      tr.dataset.examId = exam.exam_id;

      const idTd = document.createElement('td');
      idTd.textContent = exam.exam_id;

      const userTd = document.createElement('td');
      const userInput = document.createElement('input');
      userInput.type = 'text';
      userInput.className = 'form-control form-control-sm exam-username';
      userInput.placeholder = 'Username';
      userInput.value = exam.user_name || '';
      userTd.appendChild(userInput);

      const questionTd = document.createElement('td');
      const questionBtn = document.createElement('button');
      questionBtn.className = 'btn btn-sm btn-outline-secondary w-100';
      questionBtn.innerHTML = '<i class="bi bi-question-circle"></i> Question';
      questionTd.appendChild(questionBtn);

      const syncTd = document.createElement('td');
      const syncBtn = document.createElement('button');
      syncBtn.className = 'btn btn-sm btn-primary w-100';
      syncBtn.innerHTML = '<i class="bi bi-arrow-repeat"></i> Sync';
      syncTd.appendChild(syncBtn);

      const statusTd = document.createElement('td');
      statusTd.className = 'small exam-status';

      tr.append(idTd, userTd, questionTd, syncTd, statusTd);
      tbody.appendChild(tr);

      let savedValue = userInput.value;

      async function saveUsername() {
        const value = userInput.value.trim();
        if (value === savedValue) {
          return true;
        }
        try {
          await api('api/exams.php', {
            method: 'POST',
            body: JSON.stringify({ exam_id: exam.exam_id, user_name: value }),
          });
          savedValue = value;
          statusTd.innerHTML = '<span class="text-success">Username saved.</span>';
          return true;
        } catch (err) {
          statusTd.innerHTML = `<span class="text-danger">${err.message}</span>`;
          return false;
        }
      }

      userInput.addEventListener('blur', saveUsername);
      userInput.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
          e.preventDefault();
          userInput.blur();
        }
      });

      questionBtn.addEventListener('click', async () => {
        questionBtn.disabled = true;
        try {
          const data = await api(`api/exam_question.php?exam_id=${encodeURIComponent(exam.exam_id)}`);
          questionModalExamId.textContent = `#${exam.exam_id}`;
          questionModalBody.textContent = data.question || '(no question set)';
          questionModal.show();
        } catch (err) {
          statusTd.innerHTML = `<span class="text-danger">${err.message}</span>`;
        } finally {
          questionBtn.disabled = false;
        }
      });

      syncBtn.addEventListener('click', async () => {
        syncBtn.disabled = true;
        syncBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Syncing...';
        statusTd.innerHTML = '';

        const saved = await saveUsername();
        if (!saved) {
          syncBtn.disabled = false;
          syncBtn.innerHTML = '<i class="bi bi-arrow-repeat"></i> Sync';
          return;
        }

        try {
          const data = await api('api/exam_sync.php', {
            method: 'POST',
            body: JSON.stringify({ exam_id: exam.exam_id }),
          });

          if (data.ok) {
            statusTd.innerHTML = '<span class="text-success"><i class="bi bi-check-circle-fill"></i> Successfully granted.</span>';
          } else {
            const failures = data.results.filter((r) => !r.ok);
            statusTd.innerHTML = failures
              .map((r) => `<div class="text-danger"><i class="bi bi-x-circle-fill"></i> ${r.table} — ${r.error}</div>`)
              .join('');
          }
        } catch (err) {
          statusTd.innerHTML = `<span class="text-danger">${err.message}</span>`;
        } finally {
          syncBtn.disabled = false;
          syncBtn.innerHTML = '<i class="bi bi-arrow-repeat"></i> Sync';
        }
      });
    }
  }

  async function loadExams() {
    clearError();
    tbody.innerHTML = '<tr><td colspan="5" class="text-muted small">Loading...</td></tr>';
    try {
      const data = await api('api/exams.php');
      renderRows(data.exams || []);
    } catch (err) {
      tbody.innerHTML = '';
      showError(err.message);
    }
  }

  document.getElementById('btn-refresh-exams').addEventListener('click', loadExams);

  document.getElementById('btn-logout').addEventListener('click', async () => {
    try {
      await api('api/logout.php', { method: 'POST' });
    } finally {
      window.location.href = 'login.php';
    }
  });

  loadExams();
})();
