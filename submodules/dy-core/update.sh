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
    git push origin master --force
}


# Gets to /wp-content/plugins/dynamicpackages
cd ../

# Delete and redo /wp-content/plugins/dynamicaviation/submodules/dy-core
rm -rf ../dynamicaviation/submodules/dy-core
mkdir -p ../dynamicaviation/submodules
cp -r dy-core ../dynamicaviation/submodules

# Delete and redo /wp-content/themes/minimalizr/submodules/dy-core
rm -rf ../../themes/minimalizr/submodules/dy-core
mkdir -p ../../themes/minimalizr/submodules
cp -r dy-core ../../themes/minimalizr/submodules

# Return to the original directory
cd -
cd ../
perform_git_actions
cd ../dynamicaviation
perform_git_actions
cd ../../themes/minimalizr
perform_git_actions