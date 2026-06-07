<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/Logger.php';

/**
 * EmailSender with SMTP Rotation Pool
 *
 * Automatically rotates between multiple SMTP accounts to avoid Gmail's
 * 500 emails/day limit. Configure multiple accounts via SMTP_ACCOUNTS in .env.
 * Rotation occurs when an account reaches SMTP_ROTATION_LIMIT (default 450).
 */
class EmailSender
{
    private $mailer;
    private $logger;
    private $accounts;          // Array of SMTP account configs
    private $currentIndex;      // Which account we're currently using
    private $sendCounts;        // Per-account send count tracker

    public function __construct()
    {
        $this->logger = new Logger();
        $this->accounts = SMTP_ACCOUNTS;
        $this->currentIndex = 0;
        $this->sendCounts = array_fill(0, count($this->accounts), 0);

        $this->mailer = new PHPMailer(true);
        $this->configureMailer($this->currentIndex);
    }

    /**
     * Configure PHPMailer with the given account index from the pool.
     */
    private function configureMailer(int $idx): void
    {
        $account = $this->accounts[$idx];
        try {
            $this->mailer->clearAllRecipients();
            $this->mailer->isSMTP();
            $this->mailer->Host       = $account['host'];
            $this->mailer->SMTPAuth   = true;
            $this->mailer->Username   = $account['username'];
            $this->mailer->Password   = $account['password'];
            $this->mailer->SMTPSecure = $account['secure'];
            $this->mailer->Port       = (int) $account['port'];
            $this->mailer->setFrom($account['username'], COLLEGE_NAME . ' Attendance');
            $this->logger->info("SMTP Pool: Using account [{$idx}] ({$account['username']})");
        } catch (Exception $e) {
            $this->logger->error("Mailer Config Error for account [{$idx}]: {$this->mailer->ErrorInfo}");
        }
    }

    /**
     * Rotate to the next available SMTP account when limit is reached.
     */
    private function rotateIfNeeded(): void
    {
        $limit = SMTP_ROTATION_LIMIT;
        if ($this->sendCounts[$this->currentIndex] >= $limit) {
            $next = ($this->currentIndex + 1) % count($this->accounts);
            $this->logger->info("SMTP Pool: Account [{$this->currentIndex}] hit limit ({$limit}). Rotating to [{$next}].");
            $this->currentIndex = $next;
            $this->configureMailer($this->currentIndex);
        }
    }

    public function sendAttendanceReport($studentEmail, $studentName, $attendanceData, $date): bool
    {
        $this->rotateIfNeeded();

        try {
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($studentEmail, $studentName);
            $this->mailer->isHTML(true);
            $this->mailer->Subject = "Daily Attendance Report - " . $date;
            $this->mailer->Body    = $this->generateEmailBody($studentName, $attendanceData, $date);
            $this->mailer->send();

            $this->sendCounts[$this->currentIndex]++;
            $this->logger->info("Email sent to {$studentEmail} via account [{$this->currentIndex}] (count: {$this->sendCounts[$this->currentIndex]})");
            return true;
        } catch (Exception $e) {
            $this->logger->error("Failed to send to {$studentEmail}: {$this->mailer->ErrorInfo}");
            return false;
        }
    }

    /**
     * Send a detention notification email to a student.
     */
    public function sendDetentionNotice(string $studentEmail, string $studentName, string $month, float $percentage, int $attended, int $total): bool
    {
        $this->rotateIfNeeded();

        try {
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($studentEmail, $studentName);
            $this->mailer->isHTML(true);
            $this->mailer->Subject = "⚠️ Detention Notice - " . date('F Y', strtotime($month));
            $this->mailer->Body    = $this->generateDetentionEmailBody($studentName, $month, $percentage, $attended, $total);
            $this->mailer->send();

            $this->sendCounts[$this->currentIndex]++;
            $this->logger->info("Detention notice sent to {$studentEmail} (count: {$this->sendCounts[$this->currentIndex]})");
            return true;
        } catch (Exception $e) {
            $this->logger->error("Failed detention notice to {$studentEmail}: {$this->mailer->ErrorInfo}");
            return false;
        }
    }

    private function generateEmailBody($name, $data, $date): string
    {
        $rows = '';
        $absentCount = 0;
        $totalClasses = count($data);

        foreach ($data as $record) {
            if ($record['status'] == 'Absent') {
                $absentCount++;
            }
            $color      = ($record['status'] == 'Present') ? '#2a9d8f' : '#ef233c';
            $statusIcon = ($record['status'] == 'Present') ? '✅' : '❌';

            $rows .= "
                <tr>
                    <td style='padding: 12px; border-bottom: 1px solid #eee;'>{$record['subject']}</td>
                    <td style='padding: 12px; border-bottom: 1px solid #eee; color: {$color}; font-weight: bold;'>{$statusIcon} {$record['status']}</td>
                    <td style='padding: 12px; border-bottom: 1px solid #eee; font-size: 13px; color: #555;'>{$record['faculty']}</td>
                </tr>
            ";
        }

        if ($absentCount === 0) {
            $summaryColor = '#2a9d8f';
            $summaryMsg   = "<strong>Excellent!</strong> You were present in all classes today. Keep up the great work!";
        } else {
            $summaryColor = '#e76f51';
            $summaryMsg   = "You were marked <strong>Absent</strong> in {$absentCount} out of {$totalClasses} classes today. Please ensure regular attendance.";
        }

        return "
            <div style='font-family: \"Inter\", Arial, sans-serif; max-width: 600px; margin: 0 auto; border: 1px solid #e0e0e0; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.05);'>
                <div style='background: linear-gradient(135deg, #4361ee, #3f37c9); color: white; padding: 30px; text-align: center;'>
                    <h2 style='margin: 0; font-size: 24px;'>JD College of Engineering</h2>
                    <p style='margin: 8px 0 0; opacity: 0.9;'>Daily Attendance Summary - {$date}</p>
                </div>
                <div style='padding: 30px; background-color: white;'>
                    <p style='font-size: 16px; color: #333;'>Dear <strong>{$name}</strong>,</p>
                    <div style='background-color: " . $summaryColor . "15; border-left: 4px solid {$summaryColor}; padding: 15px; margin: 20px 0; color: #333; font-size: 15px;'>
                        {$summaryMsg}
                    </div>
                    <table style='width: 100%; border-collapse: collapse; margin-top: 20px;'>
                        <thead>
                            <tr style='background-color: #f8f9fa; text-align: left; color: #666; font-size: 12px; text-transform: uppercase;'>
                                <th style='padding: 12px; border-bottom: 2px solid #eee;'>Subject</th>
                                <th style='padding: 12px; border-bottom: 2px solid #eee;'>Status</th>
                                <th style='padding: 12px; border-bottom: 2px solid #eee;'>Faculty</th>
                            </tr>
                        </thead>
                        <tbody>
                            {$rows}
                        </tbody>
                    </table>
                    <p style='margin-top: 30px; font-size: 13px; color: #888; text-align: center; border-top: 1px solid #eee; padding-top: 20px;'>
                        This is an automated report. For any discrepancies, please contact your respective faculty.
                    </p>
                </div>
                <div style='background-color: #f8f9fa; padding: 15px; text-align: center; font-size: 12px; color: #aaa;'>
                    &copy; " . date('Y') . " JD College of Engineering &amp; Management. All rights reserved.
                </div>
            </div>
        ";
    }

    private function generateDetentionEmailBody(string $name, string $month, float $percentage, int $attended, int $total): string
    {
        $monthLabel = date('F Y', strtotime($month));
        $threshold  = DETENTION_THRESHOLD;
        $shortfall  = round($threshold - $percentage, 1);

        return "
            <div style='font-family: \"Inter\", Arial, sans-serif; max-width: 600px; margin: 0 auto; border: 1px solid #e0e0e0; border-radius: 12px; overflow: hidden;'>
                <div style='background: linear-gradient(135deg, #c0392b, #e74c3c); color: white; padding: 30px; text-align: center;'>
                    <h2 style='margin: 0; font-size: 24px;'>⚠️ Detention Notice</h2>
                    <p style='margin: 8px 0 0; opacity: 0.9;'>JD College of Engineering &amp; Management</p>
                </div>
                <div style='padding: 30px; background-color: white;'>
                    <p style='font-size: 16px; color: #333;'>Dear <strong>{$name}</strong>,</p>
                    <div style='background-color: #fdecea; border-left: 4px solid #e74c3c; padding: 15px; margin: 20px 0; color: #333;'>
                        Your attendance for <strong>{$monthLabel}</strong> has fallen below the minimum required threshold of <strong>{$threshold}%</strong>.
                        You are hereby marked for <strong>Detention</strong>.
                    </div>
                    <table style='width: 100%; border-collapse: collapse; margin-top: 20px;'>
                        <thead>
                            <tr style='background-color: #f8f9fa; color: #666; font-size: 12px; text-transform: uppercase;'>
                                <th style='padding: 12px; border-bottom: 2px solid #eee; text-align: left;'>Month</th>
                                <th style='padding: 12px; border-bottom: 2px solid #eee; text-align: center;'>Classes Attended</th>
                                <th style='padding: 12px; border-bottom: 2px solid #eee; text-align: center;'>Total Classes</th>
                                <th style='padding: 12px; border-bottom: 2px solid #eee; text-align: center;'>Your %</th>
                                <th style='padding: 12px; border-bottom: 2px solid #eee; text-align: center;'>Required %</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td style='padding: 12px; border-bottom: 1px solid #eee;'>{$monthLabel}</td>
                                <td style='padding: 12px; border-bottom: 1px solid #eee; text-align: center;'>{$attended}</td>
                                <td style='padding: 12px; border-bottom: 1px solid #eee; text-align: center;'>{$total}</td>
                                <td style='padding: 12px; border-bottom: 1px solid #eee; text-align: center; color: #e74c3c; font-weight: bold;'>{$percentage}%</td>
                                <td style='padding: 12px; border-bottom: 1px solid #eee; text-align: center; color: #2a9d8f; font-weight: bold;'>{$threshold}%</td>
                            </tr>
                        </tbody>
                    </table>
                    <div style='background-color: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; color: #333;'>
                        You need to make up <strong>{$shortfall}%</strong> more attendance. Please contact your HOD or Class Coordinator immediately.
                    </div>
                    <p style='margin-top: 20px; font-size: 13px; color: #888; text-align: center; border-top: 1px solid #eee; padding-top: 20px;'>
                        This is an automated notice. For queries, contact the Academic Office.
                    </p>
                </div>
                <div style='background-color: #f8f9fa; padding: 15px; text-align: center; font-size: 12px; color: #aaa;'>
                    &copy; " . date('Y') . " JD College of Engineering &amp; Management. All rights reserved.
                </div>
            </div>
        ";
    }
}