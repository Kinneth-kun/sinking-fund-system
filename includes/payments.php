<?php
require_once __DIR__ . '/../config/config.php';

/**
 * Get all dates a member has paid for
 */
function getMemberPaidDates(int $memberId): array {
    $db = getDB();
    $stmt = $db->prepare("SELECT covered_date FROM payment_days WHERE member_id = ? ORDER BY covered_date ASC");
    $stmt->execute([$memberId]);
    return array_column($stmt->fetchAll(), 'covered_date');
}

/**
 * Get next unpaid date for a member (starting from START_DATE)
 */
function getNextUnpaidDate(int $memberId): string {
    $paidDates = getMemberPaidDates($memberId);
    $startDate = new DateTime(START_DATE);
    $today     = new DateTime();
    $check     = clone $startDate;

    while ($check <= $today) {
        $dateStr = $check->format('Y-m-d');
        if (!in_array($dateStr, $paidDates)) {
            return $dateStr;
        }
        $check->modify('+1 day');
    }
    return $today->format('Y-m-d');
}

/**
 * Calculate how many days a given amount covers starting from next unpaid date
 */
function calculateDaysCovered(int $memberId, float $amount): array {
    $dailyRate = DAILY_RATE;
    $daysCovered = (int)floor($amount / $dailyRate);
    $remainder   = fmod($amount, $dailyRate);

    $paidDates = getMemberPaidDates($memberId);
    $startDate = new DateTime(START_DATE);
    $today     = new DateTime();

    // Find first unpaid date
    $check = clone $startDate;
    $firstUnpaid = null;
    while ($check <= $today) {
        $dateStr = $check->format('Y-m-d');
        if (!in_array($dateStr, $paidDates)) {
            $firstUnpaid = clone $check;
            break;
        }
        $check->modify('+1 day');
    }

    if (!$firstUnpaid) {
        $firstUnpaid = clone $today;
    }

    // Calculate date_to
    $dateTo = clone $firstUnpaid;
    $covered = [];
    $daysAdded = 0;
    $cursor = clone $firstUnpaid;

    while ($daysAdded < $daysCovered) {
        $dateStr = $cursor->format('Y-m-d');
        if (!in_array($dateStr, $paidDates)) {
            $covered[] = $dateStr;
            $dateTo = clone $cursor;
            $daysAdded++;
        }
        $cursor->modify('+1 day');
    }

    return [
        'days_covered' => $daysCovered,
        'date_from'    => $firstUnpaid->format('Y-m-d'),
        'date_to'      => $daysCovered > 0 ? $dateTo->format('Y-m-d') : $firstUnpaid->format('Y-m-d'),
        'dates'        => $covered,
        'remainder'    => $remainder
    ];
}

/**
 * Record a payment and mark covered days
 */
function recordPayment(int $memberId, float $amount, string $paymentDate, int $userId, string $notes = ''): array {
    $db = getDB();
    $calc = calculateDaysCovered($memberId, $amount);

    if ($calc['days_covered'] === 0) {
        return ['success' => false, 'message' => 'Amount is less than daily rate (₱' . DAILY_RATE . ')'];
    }

    try {
        $db->beginTransaction();

        $stmt = $db->prepare("
            INSERT INTO payments (member_id, amount, payment_date, days_covered, date_from, date_to, notes, recorded_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $memberId,
            $amount,
            $paymentDate,
            $calc['days_covered'],
            $calc['date_from'],
            $calc['date_to'],
            $notes,
            $userId
        ]);
        $paymentId = $db->lastInsertId();

        // Insert each covered day
        $dayStmt = $db->prepare("INSERT IGNORE INTO payment_days (member_id, payment_id, covered_date) VALUES (?, ?, ?)");
        foreach ($calc['dates'] as $date) {
            $dayStmt->execute([$memberId, $paymentId, $date]);
        }

        $db->commit();
        return [
            'success'      => true,
            'payment_id'   => $paymentId,
            'days_covered' => $calc['days_covered'],
            'date_from'    => $calc['date_from'],
            'date_to'      => $calc['date_to'],
            'remainder'    => $calc['remainder']
        ];
    } catch (Exception $e) {
        $db->rollBack();
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Get member payment status for a given month
 */
function getMemberMonthStatus(int $memberId, int $year, int $month): array {
    $db = getDB();
    $firstDay = new DateTime("$year-$month-01");
    $lastDay  = new DateTime($firstDay->format('Y-m-t'));
    $today    = new DateTime();
    $startDate = new DateTime(START_DATE);

    $status = [];
    $cursor = clone $firstDay;

    while ($cursor <= $lastDay) {
        $dateStr = $cursor->format('Y-m-d');
        $dayNum  = (int)$cursor->format('j');

        if ($cursor < $startDate) {
            $status[$dayNum] = 'not_started';
        } elseif ($cursor > $today) {
            $status[$dayNum] = 'future';
        } else {
            // Check if paid
            $stmt = $db->prepare("SELECT id FROM payment_days WHERE member_id = ? AND covered_date = ?");
            $stmt->execute([$memberId, $dateStr]);
            $status[$dayNum] = $stmt->fetch() ? 'paid' : 'unpaid';
        }

        $cursor->modify('+1 day');
    }

    return $status;
}

/**
 * Get total collected per month
 */
function getMonthlyTotals(int $year, int $month): array {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT COUNT(pd.id) * ? as total, COUNT(DISTINCT pd.member_id) as members_paid
        FROM payment_days pd
        WHERE YEAR(pd.covered_date) = ? AND MONTH(pd.covered_date) = ?
    ");
    $stmt->execute([DAILY_RATE, $year, $month]);
    return $stmt->fetch();
}

/**
 * Get dashboard summary stats
 */
function getDashboardStats(): array {
    $db = getDB();
    $today = new DateTime();
    $startDate = new DateTime(START_DATE);

    // Total days elapsed since start
    $daysElapsed = (int)$startDate->diff($today)->days + 1;
    $daysElapsed = max(1, $daysElapsed);

    // Total members
    $totalMembers = $db->query("SELECT COUNT(*) FROM members WHERE status = 'active'")->fetchColumn();

    // Expected total (all members × days elapsed × daily rate)
    $expectedTotal = $totalMembers * $daysElapsed * DAILY_RATE;

    // Actual total collected
    $actualTotal = $db->query("SELECT COUNT(*) * " . DAILY_RATE . " FROM payment_days pd JOIN members m ON m.id = pd.member_id WHERE m.status = 'active'")->fetchColumn();

    // This month's total
    $thisMonth = (int)$today->format('n');
    $thisYear  = (int)$today->format('Y');
    $monthTotal = $db->prepare("SELECT COUNT(*) * ? FROM payment_days WHERE YEAR(covered_date) = ? AND MONTH(covered_date) = ?");
    $monthTotal->execute([DAILY_RATE, $thisYear, $thisMonth]);
    $thisMonthTotal = $monthTotal->fetchColumn();

    // Top payers (most days paid)
    $topPayers = $db->query("
        SELECT m.name, COUNT(pd.id) as days_paid, COUNT(pd.id) * " . DAILY_RATE . " as total_paid
        FROM members m
        LEFT JOIN payment_days pd ON pd.member_id = m.id
        WHERE m.status = 'active'
        GROUP BY m.id, m.name
        ORDER BY days_paid DESC
        LIMIT 5
    ")->fetchAll();

    // Members with balance (unpaid days)
    $unpaidSummary = $db->query("
        SELECT m.name, m.id,
               (DATEDIFF(NOW(), '" . START_DATE . "') + 1) as total_days,
               COUNT(pd.id) as paid_days,
               ((DATEDIFF(NOW(), '" . START_DATE . "') + 1) - COUNT(pd.id)) as unpaid_days,
               ((DATEDIFF(NOW(), '" . START_DATE . "') + 1) - COUNT(pd.id)) * " . DAILY_RATE . " as balance_due
        FROM members m
        LEFT JOIN payment_days pd ON pd.member_id = m.id AND pd.covered_date >= '" . START_DATE . "' AND pd.covered_date <= NOW()
        WHERE m.status = 'active'
        GROUP BY m.id, m.name
        ORDER BY balance_due DESC
    ")->fetchAll();

    // Recent payments
    $recentPayments = $db->query("
        SELECT p.*, m.name as member_name
        FROM payments p
        JOIN members m ON m.id = p.member_id
        ORDER BY p.created_at DESC
        LIMIT 10
    ")->fetchAll();

    return [
        'total_members'   => $totalMembers,
        'days_elapsed'    => $daysElapsed,
        'expected_total'  => $expectedTotal,
        'actual_total'    => $actualTotal,
        'collection_rate' => $expectedTotal > 0 ? round(($actualTotal / $expectedTotal) * 100, 1) : 0,
        'this_month'      => $thisMonthTotal,
        'top_payers'      => $topPayers,
        'unpaid_summary'  => $unpaidSummary,
        'recent_payments' => $recentPayments,
    ];
}