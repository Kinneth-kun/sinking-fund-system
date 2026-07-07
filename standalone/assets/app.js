(function () {
  const DAILY_RATE = 5;
  const START_DATE = '2026-01-01';
  const STORAGE_KEY = 'staycationStandaloneData.v1';

  const sampleMembers = [
    'Member 1', 'Member 2', 'Member 3', 'Member 4', 'Member 5', 'Member 6',
    'Member 7', 'Member 8', 'Member 9', 'Member 10', 'Member 11', 'Member 12',
    'Member 13', 'Member 14', 'Member 15', 'Member 16', 'Member 17', 'Member 18'
  ];

  const app = {
    view: 'dashboard',
    data: loadData(),
    deferredPrompt: null
  };

  const el = id => document.getElementById(id);
  const money = value => 'PHP ' + Number(value || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  const todayISO = () => new Date().toISOString().slice(0, 10);
  const parseDate = value => new Date(value + 'T00:00:00');
  const formatDate = value => new Date(value + 'T00:00:00').toLocaleDateString('en-PH', { month: 'short', day: '2-digit', year: 'numeric' });
  const uid = prefix => prefix + '-' + Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 8);

  function daysBetween(start, end) {
    return Math.floor((parseDate(end) - parseDate(start)) / 86400000) + 1;
  }

  function addDays(dateISO, count) {
    const d = parseDate(dateISO);
    d.setDate(d.getDate() + count);
    return d.toISOString().slice(0, 10);
  }

  function loadData() {
    try {
      const saved = JSON.parse(localStorage.getItem(STORAGE_KEY) || 'null');
      if (saved && Array.isArray(saved.members) && Array.isArray(saved.payments)) return saved;
    } catch (error) {
      console.warn(error);
    }
    return { version: 1, members: [], payments: [] };
  }

  function saveData() {
    app.data.updatedAt = new Date().toISOString();
    localStorage.setItem(STORAGE_KEY, JSON.stringify(app.data));
    el('storageStatus').textContent = 'Saved ' + new Date().toLocaleTimeString('en-PH', { hour: '2-digit', minute: '2-digit' });
  }

  function activeMembers() {
    return app.data.members.filter(m => m.status !== 'inactive');
  }

  function paidDates(memberId) {
    const set = new Set();
    app.data.payments.filter(p => p.memberId === memberId).forEach(payment => {
      payment.dates.forEach(date => set.add(date));
    });
    return set;
  }

  function memberStats(member) {
    const paid = paidDates(member.id);
    const elapsed = Math.max(1, daysBetween(START_DATE, todayISO()));
    const paidToToday = [...paid].filter(date => date >= START_DATE && date <= todayISO()).length;
    const unpaid = Math.max(0, elapsed - paidToToday);
    return {
      elapsed,
      paid: paidToToday,
      unpaid,
      balance: unpaid * DAILY_RATE,
      totalPaid: paid.size * DAILY_RATE,
      rate: elapsed > 0 ? Math.round((paidToToday / elapsed) * 1000) / 10 : 0
    };
  }

  function calculatePayment(memberId, amount) {
    const daysCovered = Math.floor(Number(amount || 0) / DAILY_RATE);
    const remainder = Number(amount || 0) % DAILY_RATE;
    const paid = paidDates(memberId);
    let cursor = START_DATE;
    const covered = [];

    while (covered.length < daysCovered) {
      if (!paid.has(cursor)) covered.push(cursor);
      cursor = addDays(cursor, 1);
    }

    return {
      daysCovered,
      remainder,
      dates: covered,
      dateFrom: covered[0] || START_DATE,
      dateTo: covered[covered.length - 1] || START_DATE
    };
  }

  function stats() {
    const members = activeMembers();
    const elapsed = Math.max(1, daysBetween(START_DATE, todayISO()));
    const actual = app.data.payments.reduce((sum, p) => sum + Number(p.amount || 0), 0);
    const expected = members.length * elapsed * DAILY_RATE;
    const month = todayISO().slice(0, 7);
    const thisMonth = app.data.payments
      .filter(p => p.paymentDate.slice(0, 7) === month)
      .reduce((sum, p) => sum + Number(p.amount || 0), 0);

    return {
      members: members.length,
      elapsed,
      actual,
      expected,
      rate: expected ? Math.round((actual / expected) * 1000) / 10 : 0,
      thisMonth
    };
  }

  function setView(name) {
    app.view = name;
    document.querySelectorAll('.nav button').forEach(button => button.classList.toggle('active', button.dataset.view === name));
    el('side').classList.remove('open');
    render();
  }

  function renderTemplate(id) {
    const template = el(id);
    el('view').replaceChildren(template.content.cloneNode(true));
  }

  function render() {
    const titles = {
      dashboard: ['Dashboard', 'Overview from this device.'],
      members: ['Members', 'Add, edit, pause, and remove members.'],
      payments: ['Payments', 'Bulk payments are assigned from the first unpaid date.'],
      calendar: ['Calendar', 'Daily paid and unpaid status.'],
      reports: ['Reports', 'Totals, deficits, and member standing.'],
      backup: ['Backup', 'Move your offline data between devices.']
    };
    el('viewTitle').textContent = titles[app.view][0];
    el('viewSub').textContent = titles[app.view][1];

    if (app.view === 'dashboard') renderDashboard();
    if (app.view === 'members') renderMembers();
    if (app.view === 'payments') renderPayments();
    if (app.view === 'calendar') renderCalendar();
    if (app.view === 'reports') renderReports();
    if (app.view === 'backup') renderBackup();
  }

  function renderDashboard() {
    renderTemplate('dashboardTpl');
    const s = stats();
    document.querySelector('[data-stat="actual"]').textContent = money(s.actual);
    document.querySelector('[data-stat="expected"]').textContent = 'of ' + money(s.expected) + ' expected, ' + s.rate + '%';
    document.querySelector('[data-stat="members"]').textContent = s.members;
    document.querySelector('[data-stat="days"]').textContent = s.elapsed;
    document.querySelector('[data-stat="month"]').textContent = money(s.thisMonth);
    document.querySelector('[data-stat="monthName"]').textContent = new Date().toLocaleDateString('en-PH', { month: 'long', year: 'numeric' });

    const top = [...activeMembers()].sort((a, b) => memberStats(b).totalPaid - memberStats(a).totalPaid).slice(0, 5);
    fillList(el('topPayers'), top.map((member, index) => {
      const ms = memberStats(member);
      return row(`${index + 1}. ${member.name}`, `${ms.paid} days paid`, money(ms.totalPaid), 'green');
    }), 'No members yet.');

    const recent = [...app.data.payments].sort((a, b) => b.createdAt.localeCompare(a.createdAt)).slice(0, 8);
    fillList(el('recentPayments'), recent.map(payment => {
      const member = app.data.members.find(m => m.id === payment.memberId);
      return row(member ? member.name : 'Deleted member', `${formatDate(payment.dateFrom)} to ${formatDate(payment.dateTo)}`, money(payment.amount), 'green');
    }), 'No payments yet.');

    const search = el('dashSearch');
    const paintBalances = () => {
      const q = search.value.toLowerCase();
      const items = activeMembers().filter(m => m.name.toLowerCase().includes(q)).map(member => memberCard(member, true));
      fillList(el('balanceList'), items, 'No active members.');
    };
    search.addEventListener('input', paintBalances);
    paintBalances();
  }

  function renderMembers() {
    renderTemplate('membersTpl');
    el('addMemberBtn').addEventListener('click', () => openMemberDialog());
    const search = el('memberSearch');
    const paint = () => {
      const q = search.value.toLowerCase();
      const items = app.data.members.filter(m => m.name.toLowerCase().includes(q)).map(member => memberCard(member, false));
      fillList(el('memberList'), items, 'No members yet. Add your first member.');
    };
    search.addEventListener('input', paint);
    paint();
  }

  function renderPayments() {
    renderTemplate('paymentsTpl');
    el('payDate').value = todayISO();
    const select = el('payMember');
    select.innerHTML = '<option value="">Select member</option>' + activeMembers().map(m => `<option value="${m.id}">${escapeHtml(m.name)}</option>`).join('');

    const updateInfo = () => {
      const member = app.data.members.find(m => m.id === select.value);
      const info = el('payInfo');
      if (!member) {
        info.classList.remove('show');
        info.textContent = '';
        el('payPreview').textContent = '';
        return;
      }
      const ms = memberStats(member);
      info.classList.add('show');
      info.innerHTML = `<div>Paid days: <strong>${ms.paid}</strong></div><div>Unpaid days: <strong>${ms.unpaid}</strong></div><div>Balance due: <strong>${money(ms.balance)}</strong></div>`;
      updatePreview();
    };

    const updatePreview = () => {
      if (!select.value || !el('payAmount').value) return;
      const calc = calculatePayment(select.value, Number(el('payAmount').value));
      el('payPreview').textContent = `${calc.daysCovered} day(s), ${formatDate(calc.dateFrom)} to ${formatDate(calc.dateTo)}${calc.remainder ? ', remainder PHP ' + calc.remainder : ''}`;
    };

    select.addEventListener('change', updateInfo);
    el('payAmount').addEventListener('input', updatePreview);
    el('paymentForm').addEventListener('submit', event => {
      event.preventDefault();
      const memberId = select.value;
      const amount = Number(el('payAmount').value);
      const calc = calculatePayment(memberId, amount);
      if (!memberId || calc.daysCovered < 1) {
        toast('Select a member and enter at least PHP 5.');
        return;
      }
      app.data.payments.push({
        id: uid('pay'),
        memberId,
        amount,
        paymentDate: el('payDate').value,
        notes: el('payNotes').value.trim(),
        daysCovered: calc.daysCovered,
        dateFrom: calc.dateFrom,
        dateTo: calc.dateTo,
        dates: calc.dates,
        createdAt: new Date().toISOString()
      });
      saveData();
      toast('Payment recorded.');
      renderPayments();
    });

    renderPaymentList();
  }

  function renderPaymentList() {
    const payments = [...app.data.payments].sort((a, b) => b.createdAt.localeCompare(a.createdAt));
    fillList(el('paymentList'), payments.map(payment => {
      const member = app.data.members.find(m => m.id === payment.memberId);
      const node = document.createElement('div');
      node.className = 'payment-row';
      node.innerHTML = `<div class="row"><div class="row-main"><strong>${escapeHtml(member ? member.name : 'Deleted member')}</strong><small>${formatDate(payment.dateFrom)} to ${formatDate(payment.dateTo)} - ${payment.daysCovered} days</small></div><span class="pill green">${money(payment.amount)}</span></div><div class="row" style="margin-top:10px"><small class="muted">${escapeHtml(payment.notes || 'No notes')}</small><button class="btn danger" type="button">Delete</button></div>`;
      node.querySelector('button').addEventListener('click', () => {
        if (!confirm('Delete this payment? Covered days will be unmarked.')) return;
        app.data.payments = app.data.payments.filter(p => p.id !== payment.id);
        saveData();
        renderPaymentList();
      });
      return node;
    }), 'No payments yet.');
  }

  function renderCalendar() {
    renderTemplate('calendarTpl');
    const monthInput = el('calMonth');
    const memberInput = el('calMember');
    monthInput.value = todayISO().slice(0, 7);
    memberInput.innerHTML = '<option value="">All members</option>' + activeMembers().map(m => `<option value="${m.id}">${escapeHtml(m.name)}</option>`).join('');
    const paint = () => paintCalendar(monthInput.value, memberInput.value);
    monthInput.addEventListener('change', paint);
    memberInput.addEventListener('change', paint);
    paint();
  }

  function paintCalendar(month, memberId) {
    const [year, m] = month.split('-').map(Number);
    const first = new Date(year, m - 1, 1);
    const days = new Date(year, m, 0).getDate();
    const startOffset = (first.getDay() + 6) % 7;
    const grid = el('calendarGrid');
    grid.innerHTML = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'].map(d => `<div class="day head">${d}</div>`).join('');
    for (let i = 0; i < startOffset; i++) grid.insertAdjacentHTML('beforeend', '<div class="day blank"></div>');

    const members = memberId ? activeMembers().filter(m => m.id === memberId) : activeMembers();
    const total = members.length;
    let paidCount = 0;
    let unpaidCount = 0;

    for (let day = 1; day <= days; day++) {
      const date = `${year}-${String(m).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
      let paid = 0;
      members.forEach(member => {
        if (paidDates(member.id).has(date)) paid++;
      });
      const isFuture = date > todayISO();
      const isBeforeStart = date < START_DATE;
      let cls = 'future';
      let sub = total ? `${paid}/${total}` : '0/0';
      if (!isFuture && !isBeforeStart && total > 0) {
        if (paid >= total) cls = 'paid';
        else cls = paid > 0 ? 'unpaid' : 'unpaid';
        paidCount += paid;
        unpaidCount += total - paid;
      }
      grid.insertAdjacentHTML('beforeend', `<div class="day ${cls}"><strong>${day}</strong><small>${sub}</small></div>`);
    }

    el('calendarStats').innerHTML = `
      <article class="stat"><span>Tracked Members</span><strong>${total}</strong><small>${memberId ? 'Single member' : 'All members'}</small></article>
      <article class="stat"><span>Paid Slots</span><strong>${paidCount}</strong><small>${money(paidCount * DAILY_RATE)}</small></article>
      <article class="stat"><span>Unpaid Slots</span><strong>${unpaidCount}</strong><small>${money(unpaidCount * DAILY_RATE)}</small></article>
    `;
  }

  function renderReports() {
    renderTemplate('reportsTpl');
    const s = stats();
    document.querySelector('[data-report="grand"]').textContent = money(s.actual);
    document.querySelector('[data-report="rate"]').textContent = `${s.rate}% collection rate`;
    document.querySelector('[data-report="expected"]').textContent = money(s.expected);
    document.querySelector('[data-report="deficit"]').textContent = money(Math.max(0, s.expected - s.actual));
    document.querySelector('[data-report="payments"]').textContent = app.data.payments.length;

    const months = [];
    for (let i = 0; i < 12; i++) {
      const month = `2026-${String(i + 1).padStart(2, '0')}`;
      const collected = app.data.payments.filter(p => p.paymentDate.slice(0, 7) === month).reduce((sum, p) => sum + Number(p.amount || 0), 0);
      const days = new Date(2026, i + 1, 0).getDate();
      const expected = activeMembers().length * days * DAILY_RATE;
      months.push(row(new Date(2026, i, 1).toLocaleDateString('en-PH', { month: 'long' }), `Expected ${money(expected)}`, money(collected), collected >= expected ? 'green' : 'red'));
    }
    fillList(el('monthlyReport'), months, 'No report data.');

    fillList(el('memberReport'), activeMembers().map(member => memberCard(member, true)), 'No active members.');
  }

  function renderBackup() {
    renderTemplate('backupTpl');
    el('exportBtn').addEventListener('click', exportBackup);
    el('seedBtn').addEventListener('click', () => {
      if (app.data.members.length && !confirm('Add sample members to your current list?')) return;
      sampleMembers.forEach(name => app.data.members.push({ id: uid('mem'), name, nickname: name, status: 'active', createdAt: new Date().toISOString() }));
      saveData();
      toast('Sample members loaded.');
      setView('members');
    });
    el('importFile').addEventListener('change', importBackup);
  }

  function memberCard(member, compact) {
    const ms = memberStats(member);
    const node = document.createElement('div');
    node.className = 'member-card';
    node.innerHTML = `
      <div class="row">
        <div class="row-main">
          <strong>${escapeHtml(member.name)}</strong>
          <small>${escapeHtml(member.nickname || member.name)} - ${member.status}</small>
        </div>
        <span class="pill ${ms.unpaid ? 'red' : 'green'}">${ms.unpaid ? money(ms.balance) : 'Settled'}</span>
      </div>
      <div class="row" style="margin-top:10px">
        <small class="muted">${ms.paid}/${ms.elapsed} days paid - ${ms.rate}%</small>
        <div class="toolbar"></div>
      </div>
    `;
    const toolbar = node.querySelector('.toolbar');
    if (!compact) {
      const edit = button('Edit', 'btn');
      const toggle = button(member.status === 'inactive' ? 'Activate' : 'Pause', 'btn');
      const del = button('Delete', 'btn danger');
      edit.addEventListener('click', () => openMemberDialog(member));
      toggle.addEventListener('click', () => {
        member.status = member.status === 'inactive' ? 'active' : 'inactive';
        saveData();
        renderMembers();
      });
      del.addEventListener('click', () => {
        if (!confirm('Delete this member and their payments?')) return;
        app.data.members = app.data.members.filter(m => m.id !== member.id);
        app.data.payments = app.data.payments.filter(p => p.memberId !== member.id);
        saveData();
        renderMembers();
      });
      toolbar.append(edit, toggle, del);
    } else {
      const pay = button('Pay', 'btn gold');
      pay.addEventListener('click', () => setView('payments'));
      toolbar.append(pay);
    }
    return node;
  }

  function row(title, sub, value, tone) {
    const node = document.createElement('div');
    node.className = 'report-row';
    node.innerHTML = `<div class="row"><div class="row-main"><strong>${escapeHtml(title)}</strong><small>${escapeHtml(sub)}</small></div><span class="pill ${tone || ''}">${escapeHtml(value)}</span></div>`;
    return node;
  }

  function fillList(container, nodes, emptyText) {
    container.innerHTML = '';
    if (!nodes.length) {
      container.innerHTML = `<p class="muted">${emptyText}</p>`;
      return;
    }
    nodes.forEach(node => container.appendChild(node));
  }

  function button(text, className) {
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = className;
    btn.textContent = text;
    return btn;
  }

  function openMemberDialog(member) {
    el('memberDialogTitle').textContent = member ? 'Edit Member' : 'Add Member';
    el('memberId').value = member ? member.id : '';
    el('memberName').value = member ? member.name : '';
    el('memberNick').value = member ? member.nickname || '' : '';
    el('memberStatus').value = member ? member.status : 'active';
    el('memberDialog').showModal();
  }

  function exportBackup() {
    const blob = new Blob([JSON.stringify(app.data, null, 2)], { type: 'application/json' });
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = `staycation-savings-backup-${todayISO()}.json`;
    a.click();
    URL.revokeObjectURL(a.href);
  }

  async function importBackup(event) {
    const file = event.target.files[0];
    if (!file) return;
    const data = JSON.parse(await file.text());
    if (!Array.isArray(data.members) || !Array.isArray(data.payments)) {
      toast('Invalid backup file.');
      return;
    }
    if (!confirm('Replace current offline data with this backup?')) return;
    app.data = data;
    saveData();
    toast('Backup restored.');
    setView('dashboard');
  }

  function escapeHtml(value) {
    return String(value).replace(/[&<>"']/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[char]));
  }

  function toast(message) {
    const box = el('toast');
    box.textContent = message;
    box.hidden = false;
    clearTimeout(toast.timer);
    toast.timer = setTimeout(() => box.hidden = true, 2600);
  }

  function boot() {
    document.querySelectorAll('.nav button').forEach(button => button.addEventListener('click', () => setView(button.dataset.view)));
    el('menuBtn').addEventListener('click', () => el('side').classList.toggle('open'));
    el('cancelMember').addEventListener('click', () => el('memberDialog').close());
    el('memberForm').addEventListener('submit', event => {
      event.preventDefault();
      const id = el('memberId').value;
      const payload = {
        name: el('memberName').value.trim(),
        nickname: el('memberNick').value.trim(),
        status: el('memberStatus').value
      };
      if (!payload.name) return;
      if (id) {
        const member = app.data.members.find(m => m.id === id);
        Object.assign(member, payload);
      } else {
        app.data.members.push({ id: uid('mem'), ...payload, createdAt: new Date().toISOString() });
      }
      saveData();
      el('memberDialog').close();
      renderMembers();
    });

    window.addEventListener('beforeinstallprompt', event => {
      event.preventDefault();
      app.deferredPrompt = event;
      el('installBtn').hidden = false;
    });
    el('installBtn').addEventListener('click', async () => {
      if (!app.deferredPrompt) return;
      app.deferredPrompt.prompt();
      await app.deferredPrompt.userChoice;
      app.deferredPrompt = null;
      el('installBtn').hidden = true;
    });

    if ('serviceWorker' in navigator) {
      navigator.serviceWorker.register('./sw.js').catch(() => undefined);
    }

    render();
  }

  boot();
})();
