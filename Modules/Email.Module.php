<?php

namespace atREST\Modules;

use atREST\Core;
use atREST\Module;

Core::IncludeVendor('PHPMailer/PHPMailer.php');
Core::IncludeVendor('PHPMailer/SMTP.php');
Core::IncludeVendor('PHPMailer/Exception.php');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class Email
{
    use Module;

    // Constants

    const Tag   = 'Email';

    const ServerParameterName     = 'Email.Server';
    const UsernameParameterName = 'Email.Username';
    const PasswordParameterName = 'Email.Password';
    const PortParameterName     = 'Email.Port';

    // Constructors & Destructors

    public function __construct()
    {
        if (self::$serverAddress != '') {
            return;
        }

        self::$serverAddress = Core::Configuration(self::ServerParameterName);
        self::$serverPort = Core::Configuration(self::PortParameterName);
        self::$loginUsername = Core::Configuration(self::UsernameParameterName);
        self::$loginPassword = Core::Configuration(self::PasswordParameterName);
    }

    // Public Methods

    public function From(string $emailAddress, string $senderName = '', string $replyEmail = '', string $replyName = '')
    {
        $this->fromEmail = $emailAddress;
        $this->fromName = $senderName;
        $this->replyEmail = $replyEmail;
        $this->replyName = $replyName;

        return $this;
    }

    public function AddRecipient(string $toEmail, string $toName = '')
    {
        $this->recipients[$toEmail] = $toName;
        return $this;
    }

    public function Send(string $emailSubject, string $emailContent)
    {
        $phpMailer = new PHPMailer(true);

        $phpMailer->isSMTP();
        $phpMailer->CharSet = 'UTF-8';
        //$phpMailer->SMTPDebug = 2;
        $phpMailer->SMTPAuth = true;
        $phpMailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $phpMailer->Hostname = substr($this->fromEmail, strpos($this->fromEmail, '@') + 1);
        $phpMailer->Host = self::$serverAddress;
        $phpMailer->Port = self::$serverPort;
        $phpMailer->Username = self::$loginUsername;
        $phpMailer->Password = self::$loginPassword;

        $phpMailer->setFrom($this->fromEmail, $this->fromName);

        if ($this->replyEmail) {
            $phpMailer->addReplyTo($this->replyEmail, $this->replyName);
        }

        foreach ($this->recipients as $email => $name) {
            $phpMailer->addAddress($email, $name);
        }

        $phpMailer->isHTML(true);
        $phpMailer->Subject = $emailSubject;
        $phpMailer->Body = $emailContent;
        $phpMailer->AltBody = strip_tags(str_replace(array('<br>', '</p>'), array(Core::NewLine, Core::NewLine), $emailContent));

        $phpMailer->send();

        return true;
    }

    // Private Members

    private $fromEmail = '';
    private $fromName = '';
    private $replyEmail = '';
    private $replyName = '';
    private $recipients = array();

    private static $serverAddress = '';
    private static $serverPort = 0;
    private static $loginUsername = '';
    private static $loginPassword = '';
}
