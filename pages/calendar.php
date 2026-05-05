<?php
$pageTitle = 'Calendar';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/payments.php';

$db = getDB();

// Filters
$selMonth  = (int)($_GET['month']     ?? date('n'));
$selYear   = (int)($_GET['year']      ?? date('Y'));
$selMember = (int)($_GET['member_id'] ?? 0);

// Clamp to valid month
if ($selMonth < 1) $selMonth = 1;
if ($selMonth > 12) $selMonth = 12;

$members = $db->query("SELECT id, name FROM members WHERE status='active' ORDER BY name")->fetchAll();

// Days in month
$firstDay  = new DateTime("$selYear-$selMonth-01");
$daysInMonth = (int)$firstDay->format('t');
$startWeekday = (int)$firstDay->format('N'); // 1=Mon, 7=Sun
$startDate = new DateTime(START_DATE);
$today = new DateTime();

// Months for nav
$months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];

// Build payment matrix if a member is selected
$dayStatus = [];
if ($selMember) {
    $dayStatus = getMemberMonthStatus($selMember, $selYear, $selMonth);
}

// All-members summary for the month (how many paid each day)
$allMemberStatus = [];
if (!$selMember) {
    $stmt = $db->prepare("
        SELECT DAY(pd.covered_date) as day_num, COUNT(DISTINCT pd.member_id) as count
        FROM payment_days pd
        WHERE YEAR(pd.covered_date) = ? AND MONTH(pd.covered_date) = ?
        GROUP BY day_num
    ");
    $stmt->execute([$selYear, $selMonth]);
    foreach ($stmt->fetchAll() as $row) {
        $allMemberStatus[$row['day_num']] = (int)$row['count'];
    }
}

$totalMembers = count($members);
?>

<!-- Month / Member Filter -->
<div style="display:flex;align-items:center;gap:12px;margin-bottom:20px;flex-wrap:wrap">
  <!-- Month selector -->
  <div style="display:flex;gap:4px;flex-wrap:wrap">
    <?php foreach ($months as $mi => $mname):
      $mn = $mi + 1;
      $active = $mn === $selMonth && $selYear === 2026;
    ?>
    <a href="?month=<?= $mn ?>&year=<?= $selYear ?>&member_id=<?= $selMember ?>"
       class="btn btn-sm <?= $active ? 'btn-gold' : 'btn-ghost' ?>"><?= $mname ?></a>
    <?php endforeach; ?>
  </div>

  <div style="margin-left:auto;display:flex;gap:8px;align-items:center">
    <select class="form-control" style="width:180px;padding:8px 10px;font-size:13px;"
            onchange="location.href='?month=<?= $selMonth ?>&year=<?= $selYear ?>&member_id='+this.value">
      <option value="">📅 All Members View</option>
      <?php foreach ($members as $m): ?>
      <option value="<?= $m['id'] ?>" <?= $m['id']==$selMember?'selected':'' ?>><?= htmlspecialchars($m['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
</div>

<div class="stat-grid" style="grid-template-columns:repeat(4,1fr)" >
  <?php
  // Month stats
  $monthStats = getMonthlyTotals($selYear, $selMonth);
  $expectedDays = min($daysInMonth, max(0, (int)(new DateTime())->diff($firstDay->modify('+0days'))->days+1));
  ?>
  <div class="stat-card gold">
    <div class="stat-label">Month Total</div>
    <div class="stat-value">₱<?= number_format($monthStats['total'] ?? 0, 2) ?></div>
    <div class="stat-sub"><?= $months[$selMonth-1] ?> <?= $selYear ?></div>
  </div>
  <div class="stat-card green">
    <div class="stat-label">Paying Members</div>
    <div class="stat-value green"><?= $monthStats['members_paid'] ?? 0 ?></div>
    <div class="stat-sub">out of <?= $totalMembers ?></div>
  </div>
  <div class="stat-card blue">
    <div class="stat-label">Days in Month</div>
    <div class="stat-value blue"><?= $daysInMonth ?></div>
    <div class="stat-sub"><?= $months[$selMonth-1] ?> <?= $selYear ?></div>
  </div>
  <div class="stat-card gold">
    <div class="stat-label">Expected (full)</div>
    <div class="stat-value">₱<?= number_format($daysInMonth * $totalMembers * 5, 2) ?></div>
    <div class="stat-sub">if all members pay</div>
  </div>
</div>

<?php if ($selMember):
  // Single member view
  $member = $db->prepare("SELECT * FROM members WHERE id = ?");
  $member->execute([$selMember]);
  $member = $member->fetch();

  $paidCount   = array_count_values($dayStatus)['paid'] ?? 0;
  $unpaidCount = array_count_values($dayStatus)['unpaid'] ?? 0;
?>
<div class="card mb-6">
  <div class="section-header mb-4">
    <div style="display:flex;align-items:center;gap:12px">
      <div class="avatar-sm" style="width:42px;height:42px;font-size:16px"><?= strtoupper(substr($member['name'],0,1)) ?></div>
      <div>
        <div class="section-title"><?= htmlspecialchars($member['name']) ?>'s Calendar</div>
        <div class="text-muted" style="font-size:12px"><?= $months[$selMonth-1] ?> <?= $selYear ?> · ₱5/day</div>
      </div>
    </div>
    <div style="display:flex;gap:14px;font-size:13px">
      <span>✅ <strong class="text-success"><?= $paidCount ?></strong> paid</span>
      <span>❌ <strong class="text-danger"><?= $unpaidCount ?></strong> unpaid</span>
      <?php if ($unpaidCount > 0): ?>
      <a href="payments.php?member_id=<?= $selMember ?>" class="btn btn-gold btn-sm">💵 Pay ₱<?= number_format($unpaidCount * 5, 0) ?></a>
      <?php endif; ?>
    </div>
  </div>

  <!-- Day grid -->
  <div class="day-grid">
    <!-- Weekday headers -->
    <?php foreach (['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $wd): ?>
    <div class="day-cell header"><?= $wd ?></div>
    <?php endforeach; ?>

    <!-- Empty cells before month start -->
    <?php for ($e = 1; $e < $startWeekday; $e++): ?>
    <div class="day-cell" style="visibility:hidden"></div>
    <?php endfor; ?>

    <!-- Days -->
    <?php for ($d = 1; $d <= $daysInMonth; $d++):
      $status = $dayStatus[$d] ?? 'future';
      $cssClass = match($status) {
        'paid'        => 'paid',
        'unpaid'      => 'unpaid',
        'future'      => 'future',
        'not_started' => 'not-started',
        default       => 'future'
      };
      $icon = match($status) { 'paid'=>'✓','unpaid'=>'✗', default=>'' };
    ?>
    <div class="day-cell <?= $cssClass ?>" title="<?= $months[$selMonth-1] ?> <?= $d ?> — <?= ucfirst($status) ?>">
      <span><?= $d ?></span>
      <?php if ($icon): ?><span style="font-size:9px"><?= $icon ?></span><?php endif; ?>
    </div>
    <?php endfor; ?>
  </div>

  <!-- Legend -->
  <div style="display:flex;gap:16px;margin-top:16px;flex-wrap:wrap;font-size:12px">
    <span><span class="badge badge-success">✓</span> Paid (₱5)</span>
    <span><span class="badge badge-danger">✗</span> Unpaid (owes ₱5)</span>
    <span><span class="badge badge-info">—</span> Future</span>
  </div>
</div>

<?php else:
  // All-members grid view
?>

<div class="card">
  <div class="section-header mb-4">
    <span class="section-title">📆 <?= $months[$selMonth-1] ?> <?= $selYear ?> — All Members Overview</span>
    <span class="text-muted" style="font-size:12px">Color = payers that day / <?= $totalMembers ?> total</span>
  </div>

  <!-- Day-by-day summary -->
  <div style="overflow-x:auto">
    <div style="display:flex;gap:4px;flex-wrap:wrap">
      <?php for ($d = 1; $d <= $daysInMonth; $d++):
        $dateCheck = new DateTime("$selYear-$selMonth-$d");
        $count = $allMemberStatus[$d] ?? 0;
        $pct   = $totalMembers > 0 ? $count / $totalMembers : 0;

        if ($dateCheck < $startDate) {
          $color = 'rgba(255,255,255,0.04)'; $textColor = 'var(--text2)';
        } elseif ($dateCheck > $today) {
          $color = 'rgba(74,157,240,0.08)'; $textColor = 'var(--text2)';
        } elseif ($pct >= 1) {
          $color = 'rgba(78,203,141,0.25)'; $textColor = 'var(--success)';
        } elseif ($pct >= 0.5) {
          $color = 'rgba(240,165,0,0.2)'; $textColor = 'var(--warning)';
        } else {
          $color = $count > 0 ? 'rgba(231,76,106,0.2)' : 'rgba(231,76,106,0.08)';
          $textColor = $count > 0 ? 'var(--danger)' : 'var(--text2)';
        }
      ?>
      <div style="background:<?= $color ?>;border-radius:8px;padding:8px 6px;min-width:44px;text-align:center;cursor:pointer;border:1px solid rgba(255,255,255,0.05)"
           title="<?= $months[$selMonth-1] ?> <?= $d ?> — <?= $count ?>/<?= $totalMembers ?> paid"
           onclick="location.href='calendar.php?month=<?= $selMonth ?>&year=<?= $selYear ?>'">
        <div style="font-size:11px;color:var(--text2)"><?= $d ?></div>
        <div style="font-size:13px;font-weight:700;color:<?= $textColor ?>"><?= $count ?></div>
        <div style="font-size:9px;color:var(--text2)"><?= $dateCheck <= $today && $dateCheck >= $startDate ? '/' . $totalMembers : '—' ?></div>
      </div>
      <?php endfor; ?>
    </div>
  </div>

  <div style="margin-top:20px">
    <div class="section-title" style="margin-bottom:14px;font-size:15px">Member Details — <?= $months[$selMonth-1] ?></div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Member</th>
            <?php for ($d=1;$d<=$daysInMonth;$d++): ?><th style="min-width:30px;padding:8px 4px;text-align:center;font-size:10px"><?= $d ?></th><?php endfor; ?>
            <th>Paid</th>
            <th>Owes</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($members as $m):
            $ms = getMemberMonthStatus($m['id'], $selYear, $selMonth);
            $paid = array_count_values($ms)['paid'] ?? 0;
            $unpaid = array_count_values($ms)['unpaid'] ?? 0;
          ?>
          <tr>
            <td style="white-space:nowrap;font-weight:600"><?= htmlspecialchars($m['name']) ?></td>
            <?php for ($d=1;$d<=$daysInMonth;$d++):
              $s = $ms[$d] ?? 'future';
              $bg = match($s) { 'paid'=>'rgba(78,203,141,.2)', 'unpaid'=>'rgba(231,76,106,.2)', default=>'transparent' };
              $icon = match($s) { 'paid'=>'✓', 'unpaid'=>'✗', default=>'' };
            ?>
            <td style="text-align:center;background:<?= $bg ?>;font-size:10px;padding:6px 2px"><?= $icon ?></td>
            <?php endfor; ?>
            <td style="text-align:center;color:var(--success);font-weight:700"><?= $paid ?></td>
            <td style="text-align:center;<?= $unpaid>0?'color:var(--danger);font-weight:700':'' ?>">
              <?= $unpaid > 0 ? '₱'.($unpaid*5) : '✅' ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>