# Adding tutorial / scenario entries to the docs

This document explains how to add a new tutorial (scenario) so it becomes part of the generated documentation data (`documentation/assets/data/commands.json`). There are two ways: manually editing `scripts/parse-ml.js`, or using the helper script added to the repo which automates the change.

## Recommended: Programmatic method (quick)

1. Use the helper script at `scripts/add-scenario.js`.
2. Example usage:

```
node scripts/add-scenario.js --title "My New Scenario" \
  --step "Run ml wb --export" \
  --step "Select DB names" \
  --step "Confirm and check C:\\ML CLI\\Exports"

npm run docs:build
```

3. After `npm run docs:build` the scenario will appear in `documentation/assets/data/commands.json` under `tutorials.scenarios`.

Notes:
- The script inserts a new object into the `scenarios` array inside `scripts/parse-ml.js`. It will keep the file JS syntax intact but does not dedupe: avoid adding the same title multiple times.
- If you manage scenarios in a different file, you can pass `--file path/to/parse-ml.js` to the script.

## Manual method (edit the generator)

1. Open `scripts/parse-ml.js`.
2. Find the `tutorials` / `scenarios` declaration (look for `scenarios: [`).
3. Add a new object of the same shape, for example:

```
{
  title: 'My New Scenario',
  steps: [
    'Run ml wb --export',
    'Select DB names',
    'Confirm and check C:\\ML CLI\\Exports'
  ]
},
```

4. Save and run `npm run docs:build`.

## How to ask the assistant (so it updates parse-ml.js for you)

You can request the assistant to add a scenario by saying something like:

"Please add a new scenario titled 'My New Scenario' with steps: Run ml wb --export; Select DB names; Confirm export." 

When you ask, the assistant will run `scripts/add-scenario.js` (or edit `scripts/parse-ml.js` directly) and run the docs build if you request it.

## Verifying the addition

Run this quick check after building docs to see the steps printed on the console:

```
node -e "const s=require('./documentation/assets/data/commands.json').tutorials.scenarios; const sc=s.find(x=>x.title==='My New Scenario'); if(sc) sc.steps.forEach(s=>console.log(s)); else console.log('NOT FOUND');"
```

## Troubleshooting

- If the new scenario doesn't show up, ensure `npm run docs:build` completed without errors. The docs build regenerates `commands.json` from `scripts/parse-ml.js`.
- If you added an identical title multiple times, remove duplicates by editing `scripts/parse-ml.js`.
