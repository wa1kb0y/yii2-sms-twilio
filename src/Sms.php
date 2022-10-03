<?php

namespace walkboy\sms\twilio;

use Yii;
use yii\base\InvalidConfigException;
use yii\helpers\BaseStringHelper;
use walkboy\sms\BaseSms;

use Twilio\Exceptions\RestException;
use Twilio\Exceptions\TwilioException;
use Twilio\Rest\Client;

/**
 * Sms is a wrapper component for the Twilio SDK.
 *
 * To use Sms, you should configure it in the application configuration like the following:
 *
 * ```php
 * [
 *     'components' => [
 *         'sms' => [
 *             'class' => 'walkboy\sms\twilio\Sms',
 *             'viewPath' => '@common/sms',     // Optional: defaults to '@app/sms'
 *
 *             // send all sms to a file by default. You have to set
 *             // 'useFileTransport' to false and configure $messageConfig['from'],
 *             // 'sid', and 'token'
 *             'useFileTransport' => true,
 *
 *             'messageConfig' => [
 *                 'from' => '+15552228888',  // Your Twilio number (full or shortcode)
 *             ],
 *
 *             // Find your Account Sid and Auth Token at twilio.com/console
 *             'sid' => 'ACXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX',
 *             'token' => 'your_auth_token'
 *
 *             // Tell Twilio where to POST information about your message.
 *             // @see https://www.twilio.com/docs/sms/send-messages#monitor-the-status-of-your-message
 *             'statusCallback' => 'https://example.com/path/to/callback',      // optional
 *         ],
 *         // ...
 *     ],
 *     // ...
 * ],
 * ```
 *
 * To send an SMS, you may use the following code:
 *
 * ```php
 * Yii::$app->sms->compose('test-message', ['user' => $user])
 *     ->setFrom('12345')       // Your Twilio number (shortcode or full number)
 *     ->setTo('+15552224444')  // Full number including '+' and country code
 *     ->send();
 * ```
 *
 * -- or --
 *
 * ```php
 * Yii::$app->sms->compose()
 *     ->setFrom('12345')       // Your Twilio number (shortcode or full number)
 *     ->setTo('+15552224444')  // Full number including '+' and country code
 *     ->setMessage('Hello ' . $name . ', This is a test message!')
 *     ->send();
 * ```
 *
 */
class Sms extends BaseSms
{
    /**
     * @var string message default class name.
     */
    public $messageClass = 'walkboy\sms\twilio\Message';

    //public $from;

    public $sid;

    public $token;

    public $statusCallback;

    private $_twilioClient;

    public function init()
    {
        if ( $this->useFileTransport === false )
        {
            if ( ! isset($this->sid) || empty($this->sid) ) {
                throw new InvalidConfigException(self::class . ": Twilio 'sid' configuration parameter is required!");
            }

            if ( ! isset($this->token) || empty($this->token) ) {
                throw new InvalidConfigException(self::class . ": Twilio 'token' configuration parameter is required!");
            }
        }

        parent::init();
    }

    /**
     * @inheritdoc
     */
    protected function sendMessage($message)
    {
        /* @var $message Message */
        try {
            $from = $message->getFrom();
            $to = $message->getTo();

            if ( ! isset($from) || empty($from) ) {
                throw new InvalidConfigException(self::class . ": Invalid 'from' phone number!");
            }

            if ( ! isset($to) || empty($to) ) {
                throw new InvalidConfigException(self::class . ": Invalid 'to' phone number!");
            }

            $client = new Client($this->sid, $this->token);

            $payload = [
                'from' => $from,
                'body' => $message->toString()
            ];

            if ( isset($this->statusCallback) && ! empty($this->statusCallback) ) {
                $payload['statusCallback'] = $this->statusCallback;
            }

            if ( isset($message->mediaUrl) && ! empty($message->mediaUrl) ) {
                $payload['mediaUrl'] = $message->mediaUrl;
            }

            $result = $client->messages->create(
                $to,
                $payload
            );

            return $result;

        } catch (InvalidConfigException $e) {
            file_put_contents(Yii::getAlias('@runtime') . '/logs/sms-exception.log', '[' . date('m-d-Y h:i:s a', time()) . '] SMS Failed - Phone: ' . $to . PHP_EOL . $e->getMessage() . PHP_EOL . '---' . PHP_EOL, FILE_APPEND | LOCK_EX);
        } catch (TwilioException $e) {
            file_put_contents(Yii::getAlias('@runtime') . '/logs/twilio-exception.log', '[' . date('m-d-Y h:i:s a', time()) . '] SMS Failed - Phone: ' . $to . PHP_EOL . $e->getMessage() . PHP_EOL . '---' . PHP_EOL, FILE_APPEND | LOCK_EX);
        } catch (RestException $e) {
            file_put_contents(Yii::getAlias('@runtime') . '/logs/twilio-rest-exception.log', '[' . date('m-d-Y h:i:s a', time()) . '] SMS Failed - Phone: ' . $to . PHP_EOL . $e->getMessage() . PHP_EOL . '---' . PHP_EOL, FILE_APPEND | LOCK_EX);
        } catch (\Exception $e) {
            file_put_contents(Yii::getAlias('@runtime') . '/logs/sms-exception.log', '[' . date('m-d-Y h:i:s a', time()) . '] SMS Failed - Phone: ' . $to . PHP_EOL . $e->getMessage() . PHP_EOL . '---' . PHP_EOL, FILE_APPEND | LOCK_EX);
        }

        return false;
    }

    /**
     * @return \Client Twilio Client instance
     */
    public function getTwilioClient()
    {
        if (!is_object($this->_twilioClient)) {
            $this->_twilioClient = $this->createTwilioClient();
        }

        return $this->_twilioClient;
    }

    /**
     * Creates Twilio Client instance.
     * @return \Client twilio instance.
     */
    protected function createTwilioClient()
    {
        return new Client($this->sid, $this->token);
    }

}
