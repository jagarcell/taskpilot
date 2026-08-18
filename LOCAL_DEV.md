# Local Development Rules

## Terminal Commands
Use user jagarcell to run all terminal commands within the Sail environment, don't run them as root.

## Post-Approval Completion
Once any approved change has been completed, run buildapp as user jagarcell in the Sail environment.
If buildapp reports any test errors, fix all reported errors and rerun buildapp as user jagarcell in the Sail environment.

## Mandatory Pre-Completion Build Gate
If any code, config, test, migration, or asset file was changed in the current task, the agent must run buildapp before sending the final completion message.

buildapp command sequence (exact):
- sudo -u jagarcell -H sh vendor/bin/sail artisan cache:clear
- sudo -u jagarcell -H sh vendor/bin/sail artisan view:clear
- sudo -u jagarcell -H npm run build
- sudo -u jagarcell -H sh vendor/bin/sail artisan migrate
- sudo -u jagarcell -H sh vendor/bin/sail test
- sudo -u jagarcell -H npx vitest run
- sudo -u jagarcell -H sh vendor/bin/sail restart queue reverb

If any file is edited after buildapp completes, buildapp must be run again.

The final response must include a Build Gate section listing:
- whether buildapp ran
- pass/fail status for each step
- timestamp of the run
- time reverb has been up
- time queue has been up

If buildapp was not run, the task is not complete and must be reported as blocked/incomplete.