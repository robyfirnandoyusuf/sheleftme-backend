<?php

namespace App\Console\Commands;

use App\Models\Quote;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class Telebot extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tele:start';

    /**
     * The console command description.
     *
     * @var string
     */
    private $processedMessageFile = 'app/processed_messages.txt';
    private $processedMessageIds = [];
    protected $description = 'Telegram Bot';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->url = env("WEB_PORTAL");
        $this->token = env("TELEBOT_TOKEN");
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Telegram bot is running. Press Ctrl+C to stop.');

        $processedMessageFilePath = storage_path($this->processedMessageFile);

        if (file_exists($processedMessageFilePath)) {
            $this->processedMessageIds = json_decode(file_get_contents($processedMessageFilePath), true);
        }

        $strings = [
            "amazing",
            "incredible",
            "awesome",
            "fantastic",
        ];

        $randomString = ucfirst($strings[array_rand($strings)]);

        while (true) {
            $updates = $this->getUpdates();

            foreach ($updates['result'] as $update) {
                if (!is_array($update) || !array_key_exists('message', $update)) {
                    continue;
                }

                $message = $update['message'];
                $chatId = $message['chat']['id'];
                $messageId = $message['message_id'];

                $user = $message['from'];

                $username = $user['username'];
                $firstName = $user['first_name'];
                $lastName = $user['last_name'];


                if (in_array($messageId, $this->processedMessageIds)) {
                    continue;
                }

                $text = $message['text'];

                if ($text == '/start') {
                    $this->sendMessage($chatId, 'Hello! This is your Telegram bot.');
                }

                if (stripos($text, "/quote") !== false) {
                    $this->sendMessage($chatId, 'Processing...');
                    $saved = $this->storeQuote($message);
                    $message = "Oops ! something went wrong with server bot !";

                    if ($saved != false) {
                        $countUser = Quote::whereUsername($username)->count();
                        
                        //user never make a quote (new user)
                        // if ($countUser <= 0) {
                        $profilePicturePath = $this->downloadUserProfilePicture($user, $username);
                        // }
                        $url = $this->url;
                        $message = "$randomString ! Your Quote Posted Successfully: $url/quote/$saved->id";
                    }

                    $this->sendMessage($chatId, $message);
                }

                $this->processedMessageIds[] = $messageId;

                file_put_contents($processedMessageFilePath, json_encode($this->processedMessageIds));
            }

            sleep(1);
        }
    }

    /**
     * Download and store the user's profile picture in a directory based on their username.
     * Returns the path to the stored profile picture.
     */
    private function downloadUserProfilePicture($user, $username)
    {
        $photos = $this->getUserProfilePhotos($user['id']);

        if (!empty($photos['photos'])) {
            $photo = $photos['photos'][0][0];
            $fileId = $photo['file_id'];

            $filePath = $this->getFilePath($fileId);

            $fileUrl = 'https://api.telegram.org/file/bot' . $this->token . '/' . $filePath;
            $imageData = file_get_contents($fileUrl);

            $directory = storage_path("app/public/profile_pictures/$username");
            if (!file_exists($directory)) {
                mkdir($directory, 0777, true);
            }

            $filename = $username . "_profile_picture.jpg";
            $filePath = "$directory/$filename";
            file_put_contents($filePath, $imageData);

            return $filePath;
        }

        return null;
    }

    /**
     * Get the file path of a Telegram file by its file ID.
     *
     * @param string $fileId The Telegram file ID.
     * @return string|null The file path or null if the file is not found.
     */
    private function getFilePath($fileId)
    {
        $apiUrl = "https://api.telegram.org/bot{$this->token}/getFile?file_id={$fileId}";
        $response = file_get_contents($apiUrl);

        if ($response === false) {
            return null;
        }

        $data = json_decode($response, true);

        if (isset($data['result']['file_path'])) {
            return $data['result']['file_path'];
        }

        return null;
    }

    /**
     * Get a user's profile photos.
     *
     * @param int $userId The user's Telegram user ID.
     * @param int $offset (Optional) Sequential number of the first photo to be returned.
     * @param int $limit (Optional) Limits the number of photos to be retrieved (1-100).
     * @return array|null An array of user's profile photos or null on failure.
     */
    private function getUserProfilePhotos($userId, $offset = 0, $limit = 1)
    {
        $apiUrl = "https://api.telegram.org/bot{$this->token}/getUserProfilePhotos?user_id={$userId}&offset={$offset}&limit={$limit}";
        $response = file_get_contents($apiUrl);

        if ($response === false) {
            return null; 
        }

        $data = json_decode($response, true);

        if (isset($data['result'])) {
            return $data['result'];
        }

        return null;
    }


    private function storeQuote($message)
    {

        try {
            $q = new Quote;
            $q->quote = explode('/quote', $message['text'])[1] ?? $message['text'];
            $q->username = $message['chat']['username'];
            $q->json = json_encode($message);
            $q->save();
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return false;
        }

        return $q;
    }

    private function apiRequest($method, $parameters)
    {
        $url = "https://api.telegram.org/bot" . $this->token . "/" . $method;
        $options = [
            'http' => [
                'method' => 'POST',
                'header' => 'Content-Type: application/json',
                'content' => json_encode($parameters),
            ],
        ];
        $context = stream_context_create($options);
        $result = file_get_contents($url, false, $context);

        return json_decode($result, true);
    }

    private function getUpdates()
    {
        return $this->apiRequest('getUpdates', ['offset' => 1]);
    }

    private function sendMessage($chatId, $text)
    {
        $this->apiRequest('sendMessage', [
            'chat_id' => $chatId,
            'text' => $text,
        ]);
    }
}
