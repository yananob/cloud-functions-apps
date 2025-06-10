# Pub/Sub Writer (PHP)

This Cloud Function, written in PHP, publishes messages to a Google Cloud Pub/Sub topic. It's triggered by an HTTP request.

## Setup

1.  **Clone the repository or download the files.**
2.  **Install dependencies:**
    ```bash
    composer install
    ```
3.  **Configure Project ID:**
    - Copy `configs/config.json.sample` to `configs/config.json`.
    - Edit `configs/config.json` and replace `"your-gcp-project-id"` with your actual Google Cloud Project ID.
    ```json
    {
        "project_id": "your-actual-gcp-project-id"
    }
    ```
4.  **Set up Google Cloud authentication:**
    Ensure your environment is authenticated to Google Cloud, for example, by running:
    ```bash
    gcloud auth application-default login
    ```
    Alternatively, for services like Cloud Run or Cloud Functions, the service account associated with the resource will be used automatically if it has the "Pub/Sub Publisher" role.

## Deployment

Deploy the function to Google Cloud Functions:

```bash
gcloud functions deploy pubsub-write-php \
    --runtime php82 \ # Or your preferred PHP runtime e.g., php81, php74
    --trigger-http \
    --entry-point main \
    --source . \
    --region your-gcp-region \ # e.g., us-central1
    --allow-unauthenticated # If you want to allow unauthenticated requests
```

## Usage

Once deployed, you can trigger the function via an HTTP GET request.

**Parameters:**

*   `topic` (required): The name of the Pub/Sub topic to publish to.
*   Other query parameters will be included in the JSON message payload.

**Example:**

```bash
curl "https://your-region-your-project-id.cloudfunctions.net/pubsub-write-php?topic=my-topic&message=hello&user=john"
```

This will publish the following JSON message to the `my-topic` Pub/Sub topic:
```json
{
    "topic": "my-topic",
    "message": "hello",
    "user": "john"
}
```

## Local Testing

### Using PHP's built-in server (for basic testing)

1.  Ensure you have installed dependencies: `composer install`
2.  Set up `configs/config.json` with your Project ID.
3.  Start the PHP development server (requires `google/cloud-functions-framework` to be installed globally or use the one from `vendor/bin`):
    ```bash
    # If installed globally
    functions-framework --target main --port 8080

    # Or using the vendor binary
    ./vendor/bin/functions-framework --target main --port 8080
    ```
4.  In a separate terminal, send a request:
    ```bash
    curl "http://localhost:8080/?topic=my-test-topic&data=some_value"
    ```

### Running Unit Tests

To run the unit tests:

```bash
composer test
```

This will execute the tests defined in the `tests/` directory using PHPUnit.
