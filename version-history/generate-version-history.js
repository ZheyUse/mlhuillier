#!/usr/bin/env node
/* eslint-disable no-console */
const fs = require('node:fs');
const path = require('node:path');
const { spawnSync } = require('node:child_process');

const ROOT = path.resolve(__dirname, '..');
const VERSION_FILE = path.join(ROOT, 'VERSION');
const OUTPUT_FILE = path.join(ROOT, 'documentation', 'assets', 'data', 'version-history.json');

const LOG_FIELD_DELIM = '\x1f';
const LOG_RECORD_DELIM = '\x1e';
const GIT_LOG_FORMAT = `%H${LOG_FIELD_DELIM}%h${LOG_FIELD_DELIM}%ad${LOG_FIELD_DELIM}%s${LOG_RECORD_DELIM}`;

function ensureDir(filePath) {
  fs.mkdirSync(path.dirname(filePath), { recursive: true });
}

function readLatestVersion() {
  try {
    return fs.readFileSync(VERSION_FILE, 'utf8').trim() || 'unknown';
  } catch {
    return 'unknown';
  }
}

function runGit(args) {
  const result = spawnSync('git', args, {
    cwd: ROOT,
    encoding: 'utf8',
    maxBuffer: 30 * 1024 * 1024,
  });

  if (result.error) {
    throw result.error;
  }

  if (result.status !== 0) {
    throw new Error((result.stderr || 'git command failed').trim());
  }

  return (result.stdout || '').trim();
}

function extractVersion(text) {
  const match = String(text || '').match(/\b\d+\.\d+\.\d+\b/);
  return match ? match[0] : null;
}

function isVersionMarker(text) {
  const subject = String(text || '');
  if (!extractVersion(subject)) {
    return false;
  }
  return /\b(version|release|bump|tag)\b/i.test(subject);
}

function classifyCommit(subject) {
  const text = String(subject || '');

  if (isVersionMarker(text)) return 'release';
  if (/\b(feat|feature|add|added|new|introduce|implement|support|enhance)\b/i.test(text)) return 'feature';
  if (/\b(fix|bug|resolve|patch|hotfix|correct)\b/i.test(text)) return 'fix';
  if (/\b(doc|docs|documentation|readme|guide|tutorial)\b/i.test(text)) return 'docs';
  if (/\b(refactor|cleanup|rework|restructure)\b/i.test(text)) return 'refactor';
  if (/\b(perf|performance|optimi[sz]e|speed)\b/i.test(text)) return 'performance';
  if (/\b(security|auth|vuln|xss|csrf)\b/i.test(text)) return 'security';
  if (/\b(test|qa|spec)\b/i.test(text)) return 'test';
  if (/\b(chore|build|ci|deps|dependency|upgrade)\b/i.test(text)) return 'chore';
  return 'other';
}

function readCommits() {
  const raw = runGit(['log', '--date=short', `--pretty=format:${GIT_LOG_FORMAT}`]);
  if (!raw) return [];

  return raw
    .split(LOG_RECORD_DELIM)
    .map((line) => line.trim())
    .filter(Boolean)
    .map((record) => {
      const [hash, shortHash, date, title] = record.split(LOG_FIELD_DELIM);
      return {
        hash: (hash || '').trim(),
        shortHash: (shortHash || '').trim(),
        date: (date || '').trim(),
        title: (title || '').trim(),
      };
    })
    .filter((commit) => commit.hash && commit.title);
}

function initStats() {
  return {
    release: 0,
    feature: 0,
    fix: 0,
    docs: 0,
    refactor: 0,
    performance: 0,
    security: 0,
    test: 0,
    chore: 0,
    other: 0,
  };
}

function createRelease(version, latestVersion) {
  return {
    version,
    isLatest: version === latestVersion,
    commitCount: 0,
    dateRange: { from: '', to: '' },
    stats: initStats(),
    highlights: [],
    commits: [],
  };
}

function buildReleases(commits, latestVersion) {
  const releases = [];
  const releaseIndex = new Map();

  let currentVersion = latestVersion || 'unversioned';

  const ensureRelease = (version) => {
    if (!releaseIndex.has(version)) {
      const release = createRelease(version, latestVersion);
      releaseIndex.set(version, releases.length);
      releases.push(release);
    }
    return releases[releaseIndex.get(version)];
  };

  if (!commits.length) {
    ensureRelease(currentVersion);
    return releases;
  }

  commits.forEach((commit) => {
    const markerVersion = isVersionMarker(commit.title) ? extractVersion(commit.title) : null;
    if (markerVersion && markerVersion !== currentVersion) {
      currentVersion = markerVersion;
    }

    const release = ensureRelease(currentVersion);
    const type = classifyCommit(commit.title);

    release.commits.push({
      ...commit,
      type,
    });

    release.stats[type] += 1;
  });

  releases.forEach((release) => {
    const dates = release.commits.map((x) => x.date).filter(Boolean).sort();
    release.commitCount = release.commits.length;
    release.dateRange = {
      from: dates[0] || '',
      to: dates[dates.length - 1] || '',
    };
    release.highlights = release.commits
      .filter((x) => ['release', 'feature', 'fix', 'security'].includes(x.type))
      .slice(0, 8)
      .map((x) => x.title);
  });

  return releases;
}

function buildPayload() {
  const latestVersion = readLatestVersion();
  const commits = readCommits();
  const releases = buildReleases(commits, latestVersion);

  return {
    generatedAt: new Date().toISOString(),
    latestVersion,
    source: {
      versionFile: 'VERSION',
      gitLog: 'git log --date=short --pretty=format:%H%x1f%h%x1f%ad%x1f%s%x1e',
      commitCount: commits.length,
    },
    releases,
  };
}

function run() {
  let payload;
  try {
    payload = buildPayload();
  } catch (err) {
    payload = {
      generatedAt: new Date().toISOString(),
      latestVersion: readLatestVersion(),
      source: {
        versionFile: 'VERSION',
        gitLog: 'git log --date=short --pretty=format:%H%x1f%h%x1f%ad%x1f%s%x1e',
        commitCount: 0,
        error: err instanceof Error ? err.message : String(err),
      },
      releases: [],
    };
  }

  ensureDir(OUTPUT_FILE);
  fs.writeFileSync(OUTPUT_FILE, `${JSON.stringify(payload, null, 2)}\n`, 'utf8');
  console.log(`[version-history] generated ${path.relative(ROOT, OUTPUT_FILE)} (${payload.releases.length} releases)`);
}

run();
