#!/bin/bash
set -eu

gcloud pubsub topics publish web-fetch-event --message="test!"
