<?php
$pageTitle = 'Payments';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/payments.php';

$db   = getDB();
$user = getCurrentUser();
$msg  = ''; $msgType = 'success';

$preselect = (int)($_GET['member_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'record_payment') {
        $memberId = (int)($_POST['member_id'] ?? 0);
        $amount   = (float)($_POST['amount'] ?? 0);
        $payDate  = $_POST['payment_date'] ?? date('Y-m-d');
        $notes    = trim($_POST['notes'] ?? '');

        if (!$memberId || $amount <= 0) {
            $msg = 'Please select a member and enter a valid amount.'; $msgType = 'error';
        } else {
            $result = recordPayment($memberId, $amount, $payDate, $user['id'], $notes);
            if ($result['success']) {
                $msg = "Payment recorded! Covered {$result['days_covered']} days ("
                     . date('M d', strtotime($result['date_from'])) . " – "
                     . date('M d, Y', strtotime($result['date_to'])) . ").";
                if ($result['remainder'] > 0)
                    $msg .= " ₱{$result['remainder']} remainder not counted.";
            } else {
                $msg = $result['message']; $msgType = 'error';
            }
        }
    }

    if ($action === 'delete_payment') {
        $paymentId = (int)($_POST['payment_id'] ?? 0);
        $db->prepare("DELETE FROM payment_days WHERE payment_id = ?")->execute([$paymentId]);
        $db->prepare("DELETE FROM payments WHERE id = ?")->execute([$paymentId]);
        $msg = 'Payment deleted.';
    }
}

$members = $db->query("SELECT id, name FROM members WHERE status='active' ORDER BY name")->fetchAll();

$filterMember = (int)($_GET['filter_member'] ?? $preselect);
$filterMonth  = $_GET['filter_month'] ?? '';

$whereClauses = []; $params = [];
if ($filterMember) { $whereClauses[] = 'p.member_id = ?'; $params[] = $filterMember; }
if ($filterMonth)  { $whereClauses[] = "DATE_FORMAT(p.payment_date,'%Y-%m') = ?"; $params[] = $filterMonth; }
$where = $whereClauses ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

$stmt = $db->prepare("
    SELECT p.*, m.name as member_name
    FROM payments p JOIN members m ON m.id = p.member_id
    $where ORDER BY p.created_at DESC LIMIT 200
");
$stmt->execute($params);
$payments = $stmt->fetchAll();

// Member stats for JS
$allStats = $db->query("
    SELECT m.id, m.name,
           COUNT(pd.id) as paid_days,
           (DATEDIFF(NOW(),'2026-01-01')+1) as elapsed
    FROM members m
    LEFT JOIN payment_days pd ON pd.member_id=m.id AND pd.covered_date<=NOW()
    WHERE m.status='active'
    GROUP BY m.id
")->fetchAll();
?>

<style>
/* ── CUSTOM DROPDOWN ── */
.custom-select-wrap {
  position: relative;
  user-select: none;
}
.custom-select-trigger {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  background: #1a1a2e;
  border: 1px solid rgba(255,255,255,0.12);
  border-radius: 10px;
  padding: 11px 14px;
  cursor: pointer;
  transition: border-color .2s, background .2s;
  color: var(--text);
  font-size: 14px;
  font-family: 'DM Sans', sans-serif;
}
.custom-select-trigger:hover,
.custom-select-wrap.open .custom-select-trigger {
  border-color: var(--gold);
  background: #1f2040;
}
.custom-select-trigger .placeholder { color: rgba(255,255,255,0.3); }
.custom-select-trigger .selected-text { color: var(--text); font-weight: 500; }
.custom-select-arrow {
  flex-shrink: 0;
  width: 18px; height: 18px;
  display: flex; align-items: center; justify-content: center;
  transition: transform .25s;
  color: var(--gold);
  font-size: 10px;
}
.custom-select-wrap.open .custom-select-arrow { transform: rotate(180deg); }

.custom-select-dropdown {
  position: absolute;
  top: calc(100% + 6px);
  left: 0; right: 0;
  background: #1a1a2e;
  border: 1px solid rgba(226,192,125,0.3);
  border-radius: 12px;
  overflow: hidden;
  z-index: 500;
  max-height: 280px;
  display: none;
  flex-direction: column;
  box-shadow: 0 16px 40px rgba(0,0,0,0.6);
  animation: dropIn .18s ease;
}
@keyframes dropIn {
  from { opacity: 0; transform: translateY(-8px); }
  to   { opacity: 1; transform: translateY(0); }
}
.custom-select-wrap.open .custom-select-dropdown { display: flex; }

.custom-select-search {
  padding: 10px 12px;
  border-bottom: 1px solid rgba(255,255,255,0.07);
  flex-shrink: 0;
}
.custom-select-search input {
  width: 100%;
  background: rgba(255,255,255,0.06);
  border: 1px solid rgba(255,255,255,0.1);
  border-radius: 7px;
  color: var(--text);
  font-family: 'DM Sans', sans-serif;
  font-size: 13px;
  padding: 7px 10px;
  outline: none;
}
.custom-select-search input:focus { border-color: var(--gold); }
.custom-select-search input::placeholder { color: rgba(255,255,255,0.25); }

.custom-select-options {
  overflow-y: auto;
  flex: 1;
}
.custom-select-option {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 14px;
  cursor: pointer;
  color: var(--text);
  font-size: 13.5px;
  transition: background .15s, color .15s;
  border-bottom: 1px solid rgba(255,255,255,0.04);
}
.custom-select-option:last-child { border-bottom: none; }
.custom-select-option:hover { background: rgba(226,192,125,0.1); color: var(--gold); }
.custom-select-option.selected { background: rgba(226,192,125,0.15); color: var(--gold); font-weight: 600; }
.custom-select-option.hidden { display: none; }
.custom-select-option .opt-avatar {
  width: 28px; height: 28px;
  background: linear-gradient(135deg, var(--gold), var(--gold2));
  border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  font-size: 11px; font-weight: 700; color: #0c0c18;
  flex-shrink: 0;
}
.custom-select-option .opt-placeholder { color: rgba(255,255,255,0.3); font-style: italic; }
.custom-select-option.no-results { color: rgba(255,255,255,0.3); cursor: default; justify-content: center; padding: 16px; }
.custom-select-option.no-results:hover { background: none; color: rgba(255,255,255,0.3); }

/* hidden real select (still submitted with form) */
.custom-select-wrap select.real-select {
  position: absolute;
  opacity: 0;
  pointer-events: none;
  width: 1px; height: 1px;
}

/* Filter bar dropdown (smaller) */
.filter-custom-select .custom-select-trigger { padding: 8px 12px; font-size: 13px; border-radius: 8px; }
.filter-custom-select .custom-select-dropdown { border-radius: 10px; }
.filter-custom-select .custom-select-option { padding: 8px 12px; font-size: 13px; }
</style>

<?php if ($msg): ?>
<div class="alert alert-<?= $msgType==='error'?'error':'success' ?>">
  <?= $msgType==='error'?'⚠️':'✅' ?> <?= htmlspecialchars($msg) ?>
</div>
<?php endif; ?>

<div class="grid-2 mb-6">
  <!-- ── RECORD PAYMENT CARD ── -->
  <div class="card">
    <h3 class="section-title mb-4">💵 Record Payment</h3>

    <form method="POST" id="payment-form">
      <input type="hidden" name="action" value="record_payment">

      <!-- CUSTOM MEMBER SELECT -->
      <div class="form-group">
        <label>Member *</label>
        <div class="custom-select-wrap" id="member-select-wrap">
          <!-- Hidden real select for form submission -->
          <select name="member_id" class="real-select" id="member-select" required>
            <option value="">— Select Member —</option>
            <?php foreach ($members as $m): ?>
            <option value="<?= $m['id'] ?>" <?= $m['id']==$preselect?'selected':'' ?>>
              <?= htmlspecialchars($m['name']) ?>
            </option>
            <?php endforeach; ?>
          </select>

          <!-- Visible trigger -->
          <div class="custom-select-trigger" onclick="toggleCustomSelect('member-select-wrap')">
            <span id="member-select-label" class="placeholder">— Select Member —</span>
            <span class="custom-select-arrow">▼</span>
          </div>

          <!-- Dropdown panel -->
          <div class="custom-select-dropdown">
            <div class="custom-select-search">
              <input type="text" placeholder="🔍 Search member…"
                     oninput="filterCustomSelect(this, 'member-select-wrap')"
                     onclick="event.stopPropagation()">
            </div>
            <div class="custom-select-options">
              <div class="custom-select-option opt-placeholder" data-value="">— Select Member —</div>
              <?php foreach ($members as $m): ?>
              <div class="custom-select-option <?= $m['id']==$preselect?'selected':'' ?>"
                   data-value="<?= $m['id'] ?>"
                   data-label="<?= htmlspecialchars($m['name']) ?>"
                   onclick="selectCustomOption(this, 'member-select-wrap', 'member-select', 'member-select-label', function(v){ updateMemberInfo(v); })">
                <div class="opt-avatar"><?= strtoupper(substr($m['name'],0,1)) ?></div>
                <?= htmlspecialchars($m['name']) ?>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      </div>

      <!-- Member info panel -->
      <div id="member-info" style="display:none;background:var(--dark3);border-radius:10px;padding:14px;margin-bottom:14px;">
        <div style="display:flex;justify-content:space-between;font-size:13px;">
          <span class="text-muted">Paid days:</span> <span class="text-success fw-600" id="info-paid">—</span>
        </div>
        <div style="display:flex;justify-content:space-between;font-size:13px;margin-top:5px;">
          <span class="text-muted">Unpaid days:</span> <span class="text-danger fw-600" id="info-unpaid">—</span>
        </div>
        <div style="display:flex;justify-content:space-between;font-size:13px;margin-top:5px;">
          <span class="text-muted">Balance due:</span> <span class="text-danger fw-600" id="info-balance">—</span>
        </div>
      </div>

      <div class="form-group">
        <label>Amount (₱) * <span class="text-muted" style="font-size:11px">— ₱5/day, bulk OK</span></label>
        <input type="number" name="amount" id="amount-input" class="form-control"
               placeholder="e.g. 35 for 7 days, 70 for 14 days"
               min="5" step="5" required oninput="updateCalc()">
        <div id="calc-preview" style="margin-top:8px;font-size:12px;color:var(--text2)"></div>
      </div>

      <div class="form-group">
        <label>Payment Date</label>
        <input type="date" name="payment_date" class="form-control" value="<?= date('Y-m-d') ?>">
      </div>

      <div class="form-group">
        <label>Notes (optional)</label>
        <textarea name="notes" class="form-control" placeholder="e.g. Week 2 catch-up payment"></textarea>
      </div>

      <div style="display:flex;gap:10px">
        <button type="button" class="btn btn-ghost" onclick="previewPayment()">👁 Preview</button>
        <button type="submit" class="btn btn-gold" style="flex:1">✅ Record Payment</button>
      </div>
    </form>
  </div>

  <!-- Preview Panel -->
  <div class="card" id="preview-panel" style="display:none">
    <h3 class="section-title mb-4">👁 Payment Preview</h3>
    <div id="preview-content"></div>
  </div>
</div>

<!-- ── PAYMENT HISTORY ── -->
<div class="card">
  <div class="section-header mb-4" style="flex-wrap:wrap;gap:10px">
    <span class="section-title">📋 Payment History</span>

    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">

      <!-- CUSTOM FILTER MEMBER SELECT -->
      <div class="custom-select-wrap filter-custom-select" id="filter-member-wrap" style="width:190px">
        <select class="real-select" id="filter-member-real">
          <option value="">All Members</option>
          <?php foreach ($members as $m): ?>
          <option value="<?= $m['id'] ?>" <?= $m['id']==$filterMember?'selected':'' ?>>
            <?= htmlspecialchars($m['name']) ?>
          </option>
          <?php endforeach; ?>
        </select>
        <div class="custom-select-trigger" onclick="toggleCustomSelect('filter-member-wrap')">
          <span id="filter-member-label" class="<?= $filterMember ? 'selected-text' : 'placeholder' ?>">
            <?php
            if ($filterMember) {
              foreach ($members as $m) { if ($m['id']==$filterMember) { echo htmlspecialchars($m['name']); break; } }
            } else { echo 'All Members'; }
            ?>
          </span>
          <span class="custom-select-arrow">▼</span>
        </div>
        <div class="custom-select-dropdown">
          <div class="custom-select-search">
            <input type="text" placeholder="🔍 Search…"
                   oninput="filterCustomSelect(this,'filter-member-wrap')"
                   onclick="event.stopPropagation()">
          </div>
          <div class="custom-select-options">
            <div class="custom-select-option <?= !$filterMember?'selected':'' ?>"
                 data-value="" data-label="All Members"
                 onclick="selectCustomOption(this,'filter-member-wrap','filter-member-real','filter-member-label',function(v){applyFilter('filter_member',v)})">
              All Members
            </div>
            <?php foreach ($members as $m): ?>
            <div class="custom-select-option <?= $m['id']==$filterMember?'selected':'' ?>"
                 data-value="<?= $m['id'] ?>" data-label="<?= htmlspecialchars($m['name']) ?>"
                 onclick="selectCustomOption(this,'filter-member-wrap','filter-member-real','filter-member-label',function(v){applyFilter('filter_member',v)})">
              <div class="opt-avatar"><?= strtoupper(substr($m['name'],0,1)) ?></div>
              <?= htmlspecialchars($m['name']) ?>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <input type="month" value="<?= $filterMonth ?>"
             onchange="applyFilter('filter_month',this.value)"
             class="form-control" style="width:160px;padding:8px 12px;font-size:13px;background:#1a1a2e;color:var(--text);border:1px solid rgba(255,255,255,0.1);border-radius:8px;outline:none;">
    </div>
  </div>

  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>Member</th>
          <th>Amount</th>
          <th>Days</th>
          <th>Covered Period</th>
          <th>Payment Date</th>
          <th>Notes</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($payments)): ?>
        <tr><td colspan="8" style="text-align:center;padding:30px;color:var(--text2)">No payments found.</td></tr>
        <?php endif; ?>
        <?php foreach ($payments as $i => $p): ?>
        <tr>
          <td class="text-muted" style="font-size:12px"><?= $i+1 ?></td>
          <td>
            <div style="display:flex;align-items:center;gap:8px">
              <div class="avatar-sm" style="font-size:11px"><?= strtoupper(substr($p['member_name'],0,1)) ?></div>
              <span class="fw-600"><?= htmlspecialchars($p['member_name']) ?></span>
            </div>
          </td>
          <td class="text-success fw-600">₱<?= number_format($p['amount'],2) ?></td>
          <td><span class="badge badge-gold"><?= $p['days_covered'] ?> days</span></td>
          <td class="text-muted" style="font-size:12px">
            <?= date('M d', strtotime($p['date_from'])) ?> – <?= date('M d, Y', strtotime($p['date_to'])) ?>
          </td>
          <td class="text-muted" style="font-size:12px"><?= date('M d, Y', strtotime($p['payment_date'])) ?></td>
          <td class="text-muted" style="font-size:12px;max-width:130px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
            <?= htmlspecialchars($p['notes'] ?: '—') ?>
          </td>
          <td>
            <form method="POST" onsubmit="return confirm('Delete this payment? Covered days will be unmarked.')">
              <input type="hidden" name="action" value="delete_payment">
              <input type="hidden" name="payment_id" value="<?= $p['id'] ?>">
              <button class="btn btn-danger btn-sm" type="submit">🗑️</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
/* ── Member data from PHP ── */
const memberData = {};
<?php foreach ($allStats as $ms):
  $elapsed = max(1,(int)$ms['elapsed']);
  $unpaid  = max(0,$elapsed-(int)$ms['paid_days']);
  $balance = $unpaid*5;
?>
memberData[<?= $ms['id'] ?>] = {
  name:    <?= json_encode($ms['name']) ?>,
  paid:    <?= $ms['paid_days'] ?>,
  elapsed: <?= $elapsed ?>,
  unpaid:  <?= $unpaid ?>,
  balance: <?= $balance ?>
};
<?php endforeach; ?>

/* ── Custom Select Logic ── */
function toggleCustomSelect(wrapId) {
  const wrap = document.getElementById(wrapId);
  const isOpen = wrap.classList.contains('open');
  // Close all other open selects first
  document.querySelectorAll('.custom-select-wrap.open').forEach(w => {
    if (w.id !== wrapId) closeCustomSelect(w.id);
  });
  isOpen ? closeCustomSelect(wrapId) : openCustomSelect(wrapId);
}
function openCustomSelect(wrapId) {
  const wrap = document.getElementById(wrapId);
  wrap.classList.add('open');
  // Focus search input if present
  const search = wrap.querySelector('.custom-select-search input');
  if (search) setTimeout(() => search.focus(), 60);
}
function closeCustomSelect(wrapId) {
  const wrap = document.getElementById(wrapId);
  if (!wrap) return;
  wrap.classList.remove('open');
  // Clear search
  const search = wrap.querySelector('.custom-select-search input');
  if (search) { search.value = ''; filterCustomSelect(search, wrapId); }
}

function selectCustomOption(optionEl, wrapId, realSelectId, labelId, callback) {
  const wrap      = document.getElementById(wrapId);
  const realSelect= document.getElementById(realSelectId);
  const label     = document.getElementById(labelId);
  const val       = optionEl.dataset.value;
  const lbl       = optionEl.dataset.label;

  // Update hidden select
  realSelect.value = val;

  // Update trigger label
  if (val === '') {
    label.className = 'placeholder';
    label.textContent = lbl || '— Select —';
  } else {
    label.className = 'selected-text';
    label.textContent = lbl;
  }

  // Mark selected
  wrap.querySelectorAll('.custom-select-option').forEach(o => o.classList.remove('selected'));
  optionEl.classList.add('selected');

  closeCustomSelect(wrapId);
  if (callback) callback(val);
}

function filterCustomSelect(inputEl, wrapId) {
  const q       = inputEl.value.toLowerCase();
  const wrap    = document.getElementById(wrapId);
  const options = wrap.querySelectorAll('.custom-select-option');
  let visible   = 0;
  options.forEach(opt => {
    const label = (opt.dataset.label || opt.textContent).toLowerCase();
    const show  = label.includes(q);
    opt.classList.toggle('hidden', !show);
    if (show) visible++;
  });
  // No results message
  let noRes = wrap.querySelector('.no-results');
  if (visible === 0) {
    if (!noRes) {
      noRes = document.createElement('div');
      noRes.className = 'custom-select-option no-results';
      noRes.textContent = 'No results found';
      wrap.querySelector('.custom-select-options').appendChild(noRes);
    }
    noRes.style.display = 'flex';
  } else if (noRes) {
    noRes.style.display = 'none';
  }
}

// Close dropdown when clicking outside
document.addEventListener('click', function(e) {
  if (!e.target.closest('.custom-select-wrap')) {
    document.querySelectorAll('.custom-select-wrap.open').forEach(w => closeCustomSelect(w.id));
  }
});

/* ── Member info & calc ── */
function updateMemberInfo(id) {
  const info = memberData[id];
  if (!info) { document.getElementById('member-info').style.display = 'none'; return; }
  document.getElementById('member-info').style.display = 'block';
  document.getElementById('info-paid').textContent    = info.paid + ' days';
  document.getElementById('info-unpaid').textContent  = info.unpaid + ' days';
  document.getElementById('info-balance').textContent = '₱' + info.balance.toLocaleString('en-PH',{minimumFractionDigits:2});
  updateCalc();
}

function updateCalc() {
  const amount   = parseFloat(document.getElementById('amount-input').value) || 0;
  const memberId = document.getElementById('member-select').value;
  const preview  = document.getElementById('calc-preview');
  if (!amount || !memberId) { preview.textContent = ''; return; }
  const days = Math.floor(amount / 5);
  const rem  = (amount % 5).toFixed(2);
  preview.innerHTML = `<span style="color:var(--gold)">= ${days} day${days!==1?'s':''} covered</span>`
    + (parseFloat(rem)>0 ? ` · <span style="color:var(--warning)">₱${rem} remainder</span>` : '');
}

function previewPayment() {
  const memberId = document.getElementById('member-select').value;
  const amount   = parseFloat(document.getElementById('amount-input').value);
  if (!memberId || !amount) { alert('Please select a member and enter an amount.'); return; }
  const info = memberData[memberId];
  const days = Math.floor(amount / 5);
  const rem  = (amount % 5).toFixed(2);
  const panel   = document.getElementById('preview-panel');
  const content = document.getElementById('preview-content');
  panel.style.display = 'block';
  content.innerHTML = `
    <div style="background:var(--dark3);border-radius:12px;padding:18px;margin-bottom:14px">
      <div style="font-size:13px;margin-bottom:8px"><span class="text-muted">Member:</span> <strong class="text-gold">${info?.name||'Selected'}</strong></div>
      <div style="font-size:13px;margin-bottom:8px"><span class="text-muted">Amount:</span> <strong class="text-success">₱${amount.toLocaleString('en-PH',{minimumFractionDigits:2})}</strong></div>
      <div style="font-size:13px;margin-bottom:8px"><span class="text-muted">Days covered:</span> <strong class="text-gold">${days} day${days!==1?'s':''}</strong></div>
      ${parseFloat(rem)>0?`<div style="font-size:13px;margin-bottom:8px"><span class="text-muted">Remainder:</span> <span class="text-warning">₱${rem} (not counted)</span></div>`:''}
      <div style="font-size:13px"><span class="text-muted">Weeks:</span> <strong class="text-info">${(days/7).toFixed(1)} weeks</strong></div>
    </div>
    <div class="alert alert-info" style="margin:0">ℹ️ Days assigned from first unpaid date onwards.</div>`;
}

function applyFilter(key, val) {
  const url = new URL(window.location.href);
  if (val) url.searchParams.set(key, val);
  else url.searchParams.delete(key);
  window.location.href = url.toString();
}

/* ── Init preselect ── */
const preselect = <?= $preselect ?: 'null' ?>;
if (preselect) {
  const opt = document.querySelector(`#member-select-wrap .custom-select-option[data-value="${preselect}"]`);
  if (opt) {
    document.getElementById('member-select-label').textContent = opt.dataset.label;
    document.getElementById('member-select-label').className = 'selected-text';
    opt.classList.add('selected');
    updateMemberInfo(preselect);
  }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>