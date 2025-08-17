<?php
// https://github.com/googleworkspace/php-samples/blob/main/gmail/quickstart/quickstart.php

/**
 * Copyright 2018 Google Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */
// [START gmail_quickstart]
require __DIR__ . '/../vendor/autoload.php';

if (php_sapi_name() != 'cli') {
    throw new Exception('This application must be run on the command line.');
}

use Google\Client;
use Google\Service\Gmail;

/**
 * Returns an authorized API client.
 * @return Client the authorized client object
 */
function getClient(array $scopes)
{
    $client = new Client();
    $client->setApplicationName('Gmail API PHP Quickstart');
    // $client->setScopes('https://www.googleapis.com/auth/gmail.addons.current.message.readonly');
    $client->setScopes($scopes);
    $client->setAuthConfig(__DIR__ . '/../configs/googleapi_clientsecret.json');
    $client->setAccessType('offline');
    $client->setPrompt('select_account consent');
    // $client->setRedirectUri('https://www.google.com/');
 
    // Load previously authorized token from a file, if it exists.
    // The file token.json stores the user's access and refresh tokens, and is
    // created automatically when the authorization flow completes for the first
    // time.
    $tokenPath = __DIR__ . '/credentials/googleapi_token.json';
    if (file_exists($tokenPath)) {
        $accessToken = json_decode(file_get_contents($tokenPath), true);
        $client->setAccessToken($accessToken);
    }

    // If there is no previous token or it's expired.
    if ($client->isAccessTokenExpired()) {
        echo "token expired \n";
        // Refresh the token if possible, else fetch a new one.
        if ($client->getRefreshToken()) {
            echo "getting AccessToken \n";
            $accessTokenReceived = $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
            print_r($accessTokenReceived);
        } else {
            // Request authorization from the user.
            $authUrl = $client->createAuthUrl();
            printf("Open the following link in your browser:\n%s\n", $authUrl);
            print 'Enter verification code: ';
            $authCode = trim(fgets(STDIN));

            // Exchange authorization code for an access token.
            $accessToken = $client->fetchAccessTokenWithAuthCode($authCode);
            $client->setAccessToken($accessToken);

            // Check to see if there was an error.
            if (array_key_exists('error', $accessToken)) {
                throw new Exception(join(', ', $accessToken));
            }
        }
        // Save the token to a file.
        if (!file_exists(dirname($tokenPath))) {
            mkdir(dirname($tokenPath), 0700, true);
        }
        file_put_contents($tokenPath, json_encode($client->getAccessToken()));
    }
    return $client;
}


// Get the API client and construct the service object.
$scopes = [
    Gmail::MAIL_GOOGLE_COM,
    Gmail::GMAIL_MODIFY,
    Gmail::GMAIL_READONLY,
];
$client = getClient($scopes);
$service = new Gmail($client);

try {
    // Print the labels in the user's account.
    $user = 'me';
    $results = $service->users_labels->listUsersLabels($user);

    if (count($results->getLabels()) == 0) {
        print "No labels found.\n";
    } else {
        print "Labels:\n";
        foreach ($results->getLabels() as $label) {
            printf("- %s\n", $label->getName());
        }
    }
}
catch (Exception $e) {
    // TODO(developer) - handle error appropriately
    echo $e;
    // echo 'Message: ' .$e->getMessage();
}
// [END gmail_quickstart]