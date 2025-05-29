#!/bin/bash
set -eu

gcloud pubsub topics publish web-fetch --message="test!"
