#!/bin/bash

# Check if a custom comment is provided as a command-line argument
if [ -z "$1" ]; then
    echo "Usage: $0 <custom_comment>"
    exit 1
fi

# Set the custom comment
CUSTOM_COMMENT="$1"

# Function to perform the git actions
perform_git_actions() {
    git add .
    git commit -m "$CUSTOM_COMMENT"
    git push origin master
}

# Main script

# Root directory
perform_git_actions

# Move to parent directory
cd ..

# Parent directory
perform_git_actions

# Move to dynamicaviation directory
cd ../dynamicaviation || exit
./submodules.sh

# Dynamicaviation directory
perform_git_actions

# Move to themes/minimalizr directory
cd ../../themes/minimalizr || exit
./submodules.sh

# Minimalizr directory
perform_git_actions

# Return to the original directory
cd -
