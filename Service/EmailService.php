<?php

require_once DOCUMENT_ROOT . '/PHPMailer/src/Exception.php';
require_once DOCUMENT_ROOT . '/PHPMailer/src/PHPMailer.php';
require_once DOCUMENT_ROOT . '/PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class EmailService
{
    private $mail;
    private $config;

    public function __construct()
    {
        $this->config = include DOCUMENT_ROOT . "/config/mail.php"; // Load SMTP config

        $this->mail = new PHPMailer(true);

        // SMTP Settings
        $this->mail->isSMTP();
        $this->mail->Host       = $this->config['host'];
        $this->mail->SMTPAuth   = true;
        $this->mail->Username   = $this->config['username'];
        $this->mail->Password   = $this->config['password'];
        $this->mail->SMTPSecure = $this->config['encryption'];
        $this->mail->Port       = $this->config['port'];

        // Default sender
        $this->mail->setFrom(
            $this->config['from_email'],
            $this->config['from_name']
        );
    }

    public function send($to, $subject, $body, $sendEmailToSales = true, $cc = null)
    {
        try {
            $this->mail->clearAddresses();
            $this->mail->clearCCs();
            $this->mail->addAddress($to);

            if (!empty($cc)) {
                $ccAddresses = is_array($cc) ? $cc : array($cc);
                foreach ($ccAddresses as $ccEmail) {
                    if (!empty($ccEmail)) {
                        $this->mail->addCC($ccEmail);
                    }
                }
            }

            $this->mail->isHTML(true);
            $this->mail->Subject = $subject;
            $this->mail->Body    = $body;

            $this->mail->send();

            if ($sendEmailToSales) {
                $this->mail->clearAddresses();
                $this->mail->clearCCs();
                $this->mail->addAddress($this->config['sales_email']);
                $this->mail->Subject = $subject;
                $this->mail->Body    = $body;
                $this->mail->send();
            }

            return true;
        } catch (Exception $e) {
            return "Mailer Error: " . $this->mail->ErrorInfo;
        }
    }
}
