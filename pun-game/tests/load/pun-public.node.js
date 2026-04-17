/**
 * pun 全功能压测（Node 内置 fetch，无需 k6）
 *
 * 默认仅压免鉴权接口；可通过环境变量开启鉴权接口/写接口。
 *
 * PowerShell 示例：
 *   node "pun-game/tests/load/pun-public.node.js"
 *   $env:BASE_URL="https://sofun.online"; $env:CONCURRENCY="30"; $env:DURATION_SEC="120"; node "pun-game/tests/load/pun-public.node.js"
 *   $env:AUTH_TOKEN="你的BearerToken"; $env:INCLUDE_AUTH="1"; node "pun-game/tests/load/pun-public.node.js"
 *   $env:AUTH_TOKEN="你的BearerToken"; $env:INCLUDE_AUTH="1"; $env:INCLUDE_WRITE="1"; node "pun-game/tests/load/pun-public.node.js"
 */

const BASE_URL = process.env.BASE_URL || "http://127.0.0.1:8000";
const CONCURRENCY = Number(process.env.CONCURRENCY || 20);
const DURATION_SEC = Number(process.env.DURATION_SEC || 120);
const TIMEOUT_MS = Number(process.env.TIMEOUT_MS || 10000);
const SLEEP_MS = Number(process.env.SLEEP_MS || 100);
const AUTH_TOKEN = process.env.AUTH_TOKEN || ".eyJ1c2VyX2lkIjoxLCJvcGVuaWQiOiJvTmNPQTRueWVVWU5jQmNfSkZld0xpRG5kUlZZIiwiaWF0IjoxNzc2MzA5MzU3LCJleHAiOjE4NTQwNjkzNTd9.NXhYA7Nhag3TnNbS35nCkwqpcSl7xVEdCNS77bKStAc";
const INCLUDE_AUTH = process.env.INCLUDE_AUTH === "1";
const INCLUDE_WRITE = process.env.INCLUDE_WRITE === "1";

const TEST_LEVEL = Number(process.env.TEST_LEVEL || 1);
const TEST_MID_LEVEL = Number(process.env.TEST_MID_LEVEL || 0);
const TEST_XHS_LEVEL = Number(process.env.TEST_XHS_LEVEL || 504);
const TEST_FORUM_TOPIC_ID = Number(process.env.TEST_FORUM_TOPIC_ID || 1);
const TEST_BATTLE_ROOM_ID = process.env.TEST_BATTLE_ROOM_ID || "";

/** @type {Array<{name:string, method:'GET'|'POST', path:string, auth?:boolean, body?:Record<string, any>}>} */
const PUBLIC_ENDPOINTS = [
  { name: "stats_home", method: "GET", path: "/pun/stats/home" },
  { name: "changelog_latest", method: "GET", path: "/pun/changelog/latest" },
  { name: "rank_mid", method: "GET", path: "/pun/rank/list?gameTier=mid&page=1&page_size=20" },
  { name: "rank_xhs", method: "GET", path: "/pun/rank/list?gameTier=xhs&page=1&page_size=20" },
  { name: "rank_beginner", method: "GET", path: "/pun/rank/list?gameTier=beginner&page=1&page_size=20" },
  { name: "forum_list", method: "GET", path: "/pun/forum/list?page=1&page_size=20" },
  { name: "forum_detail", method: "GET", path: `/pun/forum/detail?id=${TEST_FORUM_TOPIC_ID}&page=1&page_size=20` },
  { name: "battle_rank", method: "GET", path: "/pun/battle/rank?page=1&page_size=20" },
];

const AUTH_READ_ENDPOINTS = [
  { name: "level_progress_beginner", method: "GET", path: "/pun/level/progress?gameTier=beginner", auth: true },
  { name: "level_progress_mid", method: "GET", path: "/pun/level/progress?gameTier=mid", auth: true },
  { name: "level_progress_xhs", method: "GET", path: "/pun/level/progress?gameTier=xhs", auth: true },
  { name: "battle_history", method: "GET", path: "/pun/battle/history?page=1&page_size=20", auth: true },
];

// 写接口会变更线上数据，默认关闭。仅在测试环境或你明确接受数据污染时开启。
const AUTH_WRITE_ENDPOINTS = [
  {
    name: "answer_submit_beginner",
    method: "POST",
    path: "/pun/answer/submit",
    auth: true,
    body: { level: TEST_LEVEL, userAnswer: ["测", "试"], gameTier: "beginner" },
  },
  {
    name: "answer_submit_mid",
    method: "POST",
    path: "/pun/answer/submit",
    auth: true,
    body: { level: TEST_MID_LEVEL, userAnswer: ["测", "试"], gameTier: "mid" },
  },
  {
    name: "answer_submit_xhs",
    method: "POST",
    path: "/pun/answer/submit",
    auth: true,
    body: { level: TEST_XHS_LEVEL, userAnswer: ["测", "试"], gameTier: "xhs" },
  },
  {
    name: "reveal_hint_beginner",
    method: "POST",
    path: "/pun/level/reveal-hint",
    auth: true,
    body: { level: TEST_LEVEL, gameTier: "beginner" },
  },
  {
    name: "share_reward",
    method: "POST",
    path: "/pun/level/share-reward",
    auth: true,
    body: { add: 1 },
  },
  {
    name: "feedback_submit",
    method: "POST",
    path: "/pun/feedback/submit",
    auth: true,
    body: { type: "other", content: `load-test-${Date.now()}`, contact: "node-load-test" },
  },
  {
    name: "forum_topic_create",
    method: "POST",
    path: "/pun/forum/topic/create",
    auth: true,
    body: { title: "压测贴", content: `压测创建帖子-${Date.now()}` },
  },
  {
    name: "forum_reply_create",
    method: "POST",
    path: "/pun/forum/reply/create",
    auth: true,
    body: { topic_id: TEST_FORUM_TOPIC_ID, content: `压测回复-${Date.now()}`, reply_to_id: 0 },
  },
  {
    name: "battle_create",
    method: "POST",
    path: "/pun/battle/create",
    auth: true,
    body: {},
  },
];

const ENDPOINTS = [
  ...PUBLIC_ENDPOINTS,
  ...(INCLUDE_AUTH && AUTH_TOKEN ? AUTH_READ_ENDPOINTS : []),
  ...(INCLUDE_AUTH && INCLUDE_WRITE && AUTH_TOKEN ? AUTH_WRITE_ENDPOINTS : []),
];

const endpointNames = ENDPOINTS.map((e) => e.name);

const stats = {
  total: 0,
  ok: 0,
  fail: 0,
  statusNot200: 0,
  badBody: 0,
  timeout: 0,
  networkError: 0,
  perEndpoint: Object.fromEntries(endpointNames.map((name) => [name, { total: 0, ok: 0, fail: 0 }])),
  durations: [],
  startedAt: Date.now(),
};

function sleep(ms) {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

function percentile(sortedArray, p) {
  if (!sortedArray.length) return 0;
  const idx = Math.min(sortedArray.length - 1, Math.max(0, Math.ceil((p / 100) * sortedArray.length) - 1));
  return sortedArray[idx];
}

async function requestOnce(endpoint) {
  const url = `${BASE_URL}${endpoint.path}`;
  const controller = new AbortController();
  const timeoutId = setTimeout(() => controller.abort(), TIMEOUT_MS);
  const t0 = Date.now();

  stats.total += 1;
  stats.perEndpoint[endpoint.name].total += 1;

  try {
    /** @type {Record<string,string>} */
    const headers = {
      "Content-Type": "application/json",
    };
    if (endpoint.auth && AUTH_TOKEN) {
      headers.Authorization = `Bearer ${AUTH_TOKEN}`;
    }

    const res = await fetch(url, {
      method: endpoint.method,
      headers,
      body: endpoint.method === "POST" ? JSON.stringify(endpoint.body || {}) : undefined,
      signal: controller.signal,
    });
    const cost = Date.now() - t0;
    stats.durations.push(cost);

    if (res.status !== 200) {
      stats.fail += 1;
      stats.statusNot200 += 1;
      stats.perEndpoint[endpoint.name].fail += 1;
      return;
    }

    let body;
    try {
      body = await res.json();
    } catch (_) {
      stats.fail += 1;
      stats.badBody += 1;
      stats.perEndpoint[endpoint.name].fail += 1;
      return;
    }

    if (typeof body?.code === "undefined") {
      stats.fail += 1;
      stats.badBody += 1;
      stats.perEndpoint[endpoint.name].fail += 1;
      return;
    }

    stats.ok += 1;
    stats.perEndpoint[endpoint.name].ok += 1;
  } catch (err) {
    stats.fail += 1;
    stats.perEndpoint[endpoint.name].fail += 1;
    if (err && err.name === "AbortError") {
      stats.timeout += 1;
    } else {
      stats.networkError += 1;
    }
  } finally {
    clearTimeout(timeoutId);
  }
}

async function worker(stopAtMs) {
  while (Date.now() < stopAtMs) {
    const endpoint = ENDPOINTS[Math.floor(Math.random() * ENDPOINTS.length)];
    await requestOnce(endpoint);
    if (SLEEP_MS > 0) {
      await sleep(SLEEP_MS);
    }
  }
}

function printSummary() {
  const elapsedMs = Date.now() - stats.startedAt;
  const elapsedSec = elapsedMs / 1000;
  const rps = elapsedSec > 0 ? stats.total / elapsedSec : 0;
  const failRate = stats.total > 0 ? (stats.fail / stats.total) * 100 : 0;

  const sorted = [...stats.durations].sort((a, b) => a - b);
  const p50 = percentile(sorted, 50);
  const p95 = percentile(sorted, 95);
  const p99 = percentile(sorted, 99);
  const avg = sorted.length ? Math.round(sorted.reduce((a, b) => a + b, 0) / sorted.length) : 0;

  console.log("\n=== Load Test Summary ===");
  console.log(`BASE_URL      : ${BASE_URL}`);
  console.log(`CONCURRENCY   : ${CONCURRENCY}`);
  console.log(`DURATION_SEC  : ${DURATION_SEC}`);
  console.log(`TIMEOUT_MS    : ${TIMEOUT_MS}`);
  console.log(`INCLUDE_AUTH  : ${INCLUDE_AUTH ? "1" : "0"}`);
  console.log(`INCLUDE_WRITE : ${INCLUDE_WRITE ? "1" : "0"}`);
  console.log(`AUTH_TOKEN    : ${AUTH_TOKEN ? "已提供" : "未提供"}`);
  console.log(`ENDPOINTS     : ${ENDPOINTS.length}`);
  console.log(`TOTAL         : ${stats.total}`);
  console.log(`OK            : ${stats.ok}`);
  console.log(`FAIL          : ${stats.fail}`);
  console.log(`FAIL_RATE     : ${failRate.toFixed(2)}%`);
  console.log(`RPS           : ${rps.toFixed(2)}`);
  console.log(`LATENCY_AVG   : ${avg} ms`);
  console.log(`LATENCY_P50   : ${p50} ms`);
  console.log(`LATENCY_P95   : ${p95} ms`);
  console.log(`LATENCY_P99   : ${p99} ms`);
  console.log(`TIMEOUT       : ${stats.timeout}`);
  console.log(`STATUS_NOT_200: ${stats.statusNot200}`);
  console.log(`BAD_BODY      : ${stats.badBody}`);
  console.log(`NETWORK_ERROR : ${stats.networkError}`);

  console.log("\n--- Per Endpoint ---");
  for (const ep of ENDPOINTS) {
    const s = stats.perEndpoint[ep.name];
    const epFailRate = s.total > 0 ? ((s.fail / s.total) * 100).toFixed(2) : "0.00";
    console.log(`${ep.name} [${ep.method} ${ep.path}] | total=${s.total} ok=${s.ok} fail=${s.fail} failRate=${epFailRate}%`);
  }
}

async function main() {
  if (typeof fetch !== "function") {
    console.error("当前 Node 版本不支持全局 fetch，请使用 Node 18+。");
    process.exit(1);
  }
  if (ENDPOINTS.length === 0) {
    console.error("没有可压测的接口。若要压鉴权接口，请设置 INCLUDE_AUTH=1 并提供 AUTH_TOKEN。");
    process.exit(1);
  }
  if (INCLUDE_AUTH && !AUTH_TOKEN) {
    console.warn("INCLUDE_AUTH=1 但未提供 AUTH_TOKEN，鉴权接口已自动跳过。");
  }
  if (INCLUDE_WRITE) {
    console.warn("警告：INCLUDE_WRITE=1 会调用写接口并修改数据，请确认在测试环境执行。");
  }

  const stopAtMs = Date.now() + DURATION_SEC * 1000;
  const workers = Array.from({ length: CONCURRENCY }, () => worker(stopAtMs));
  await Promise.all(workers);
  printSummary();

  // 简单阈值判定（可按需调整）
  const failRate = stats.total > 0 ? stats.fail / stats.total : 1;
  const sorted = [...stats.durations].sort((a, b) => a - b);
  const p95 = percentile(sorted, 95);
  if (failRate >= 0.02 || p95 >= 1200) {
    process.exitCode = 2;
  }
}

main().catch((err) => {
  console.error("压测脚本异常:", err);
  process.exit(1);
});

