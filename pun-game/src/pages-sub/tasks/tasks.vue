<template>
  <view class="page">
    <view class="bg-wrap">
      <view class="bg-gradient" />
      <view class="bg-dots" />
      <view class="bg-glow" />
    </view>

    <PunPageNavBar
      :status-bar-height="statusBarHeight"
      :nav-bar-height="navBarHeight"
      :menu-button-height="menuButtonHeight"
      title="任务中心"
      @left-click="back"
    />

    <view class="section">
      <!-- 答案次数概览 -->
      <view class="quota-banner">
        <text class="quota-banner-label">当前答案次数</text>
        <view class="quota-banner-value-row">
          <text class="quota-banner-num">{{ hintAnswerQuota }}</text>
          <text class="quota-banner-unit">次</text>
        </view>
      </view>

      <!-- 每日任务 -->
      <view class="task-section">
        <view class="task-section-header task-section-header--daily">
          <view class="task-section-header-left">
            <text class="task-section-icon">📅</text>
            <text class="task-section-title">每日任务</text>
          </view>
          <text class="task-section-badge">每天零点重置</text>
        </view>
        <view class="task-row">
          <view class="task-row-info">
            <text class="task-row-icon">🎁</text>
            <view class="task-row-main">
              <text class="task-row-name">答题奖励</text>
              <view class="task-row-progress-wrap">
                <view class="task-row-progress-bar">
                  <view
                    class="task-row-progress-fill"
                    :style="{
                      width:
                        Math.min(
                          100,
                          Math.round((dailyAnswerCount / dailyAnswerRequired) * 100)
                        ) + '%',
                    }"
                  />
                </view>
                <text class="task-row-sub"
                  >{{ dailyAnswerCount }}/{{ dailyAnswerRequired }} 题 · 完成后领
                  <text class="task-row-reward">+5次</text></text
                >
              </view>
            </view>
          </view>
          <button
            class="task-btn"
            :class="
              dailyNoonTaskClaimed
                ? 'task-btn--done'
                : canClaimDailyReward
                ? 'task-btn--daily'
                : 'task-btn--goto'
            "
            :disabled="
              dailyNoonTaskClaimed || (canClaimDailyReward ? dailyClaimLoading : false)
            "
            @click.stop="onDailyRewardAction"
          >
            {{
              dailyNoonTaskClaimed
                ? "✓ 已领取"
                : canClaimDailyReward
                ? dailyClaimLoading
                  ? "领取中…"
                  : "领取"
                : "去答题"
            }}
          </button>
        </view>
        <view class="task-divider" />
        <view class="task-row">
          <view class="task-row-info">
            <text class="task-row-icon">📣</text>
            <view class="task-row-main">
              <text class="task-row-name">分享领奖励</text>
              <text class="task-row-sub"
                >今日 {{ shareDailyClaimed }}/{{ hintAnswerShareDailyMax }} 次 · 每次
                <text class="task-row-reward">+1次</text> 查看答案</text
              >
            </view>
          </view>
          <!-- #ifdef MP-WEIXIN -->
          <button
            class="task-btn"
            :class="shareTaskClaimedAll ? 'task-btn--done' : 'task-btn--share'"
            :disabled="shareTaskClaimedAll || shareClaimLoading"
            open-type="share"
            @click="onShareTaskIntent"
          >
            {{
              shareTaskClaimedAll
                ? "✓ 已完成"
                : shareClaimLoading
                ? "领取中…"
                : shareDailyClaimed > 0
                ? "再次分享"
                : "去分享"
            }}
          </button>
          <!-- #endif -->
          <!-- #ifdef MP-TOUTIAO -->
          <button
            class="task-btn"
            :class="shareTaskClaimedAll ? 'task-btn--done' : 'task-btn--share'"
            :disabled="shareTaskClaimedAll || shareClaimLoading"
            open-type="share"
            @click="onShareTaskIntent"
          >
            {{
              shareTaskClaimedAll
                ? "✓ 已完成"
                : shareClaimLoading
                ? "领取中…"
                : shareDailyClaimed > 0
                ? "再次分享"
                : "去分享"
            }}
          </button>
          <!-- #endif -->
          <!-- #ifndef MP-WEIXIN -->
          <!-- #ifndef MP-TOUTIAO -->
          <button
            class="task-btn"
            :class="shareTaskClaimedAll ? 'task-btn--done' : 'task-btn--share'"
            :disabled="shareTaskClaimedAll || shareClaimLoading"
            @click="onShareTaskIntent"
          >
            {{
              shareTaskClaimedAll
                ? "✓ 已完成"
                : shareClaimLoading
                ? "领取中…"
                : shareDailyClaimed > 0
                ? "再次分享"
                : "去分享"
            }}
          </button>
          <!-- #endif -->
          <!-- #endif -->
        </view>
        <view class="task-divider" />
        <view class="task-row">
          <view class="task-row-info">
            <text class="task-row-icon">🎬</text>
            <view class="task-row-main">
              <text class="task-row-name">看广告领答案</text>
              <text class="task-row-sub"
                >今日已完成 {{ dailyAdTaskCount }} 次 · 不限次，每次
                <text class="task-row-reward">+1次</text></text
              >
            </view>
          </view>
          <button
            class="task-btn task-btn--daily"
            :disabled="dailyAdTaskLoading"
            @click.stop="claimDailyAdTask"
          >
            {{ dailyAdTaskLoading ? "处理中…" : "去领取" }}
          </button>
        </view>
        <view class="task-divider" />
        <view class="task-row">
          <view class="task-row-info">
            <text class="task-row-icon">⚔️</text>
            <view class="task-row-main">
              <text class="task-row-name">1V1 对局任务</text>
              <view class="task-row-progress-wrap">
                <view class="task-row-progress-bar">
                  <view
                    class="task-row-progress-fill"
                    :style="{
                      width:
                        Math.min(
                          100,
                          Math.round((dailyBattleCount / dailyBattleRequired) * 100)
                        ) + '%',
                    }"
                  />
                </view>
                <text class="task-row-sub"
                  >{{ dailyBattleCount }}/{{ dailyBattleRequired }} 局 · 完成后领
                  <text class="task-row-reward">+3次</text></text
                >
              </view>
            </view>
          </view>
          <button
            class="task-btn"
            :class="
              dailyBattleTaskClaimed
                ? 'task-btn--done'
                : canClaimDailyBattleTask
                ? 'task-btn--daily'
                : 'task-btn--goto'
            "
            :disabled="
              dailyBattleTaskClaimed ||
              (canClaimDailyBattleTask ? dailyBattleTaskLoading : false)
            "
            @click.stop="onDailyBattleTaskAction"
          >
            {{
              dailyBattleTaskClaimed
                ? "✓ 已领取"
                : canClaimDailyBattleTask
                ? dailyBattleTaskLoading
                  ? "领取中…"
                  : "领取"
                : "去对局"
            }}
          </button>
        </view>
      </view>

      <!-- 永久任务 -->
      <view class="task-section">
        <view class="task-section-header task-section-header--permanent">
          <view class="task-section-header-left">
            <text class="task-section-icon">🏆</text>
            <text class="task-section-title">永久任务</text>
          </view>
          <text class="task-section-badge">限领一次</text>
        </view>
        <view class="task-row">
          <view class="task-row-info">
            <text class="task-row-icon">🖼️</text>
            <view class="task-row-main">
              <text class="task-row-name">首次设置头像</text>
              <text class="task-row-sub"
                >设置头像后可领 <text class="task-row-reward">+3次</text> 查看答案</text
              >
            </view>
          </view>
          <button
            class="task-btn"
            :class="avatarTaskClaimed ? 'task-btn--done' : 'task-btn--permanent'"
            :disabled="avatarTaskClaimed || avatarTaskClaimLoading"
            @click.stop="claimAvatarTask"
          >
            {{
              avatarTaskClaimed ? "✓ 已领取" : avatarTaskClaimLoading ? "领取中…" : "领取"
            }}
          </button>
        </view>
        <view class="task-divider" />
        <view class="task-row">
          <view class="task-row-info">
            <text class="task-row-icon">✏️</text>
            <view class="task-row-main">
              <text class="task-row-name">首次设置昵称</text>
              <text class="task-row-sub"
                >设置昵称后可领 <text class="task-row-reward">+3次</text> 查看答案</text
              >
            </view>
          </view>
          <button
            class="task-btn"
            :class="nicknameTaskClaimed ? 'task-btn--done' : 'task-btn--permanent'"
            :disabled="nicknameTaskClaimed || nicknameTaskClaimLoading"
            @click.stop="claimNicknameTask"
          >
            {{
              nicknameTaskClaimed
                ? "✓ 已领取"
                : nicknameTaskClaimLoading
                ? "领取中…"
                : "领取"
            }}
          </button>
        </view>
        <view class="task-divider" />
        <view class="task-row">
          <view class="task-row-info">
            <text class="task-row-icon">⭐</text>
            <view class="task-row-main">
              <text class="task-row-name">收藏到我的小程序</text>
              <text class="task-row-sub">
                添加到微信「我的小程序」或抖音「我的收藏」后，<text
                  class="task-row-reward"
                  >从该入口进入</text
                >
                可领 <text class="task-row-reward">+3次</text>
                <template v-if="!myMiniProgramTaskClaimed && !launchFromMyMiniOrCollect">
                  · 请关闭小程序后从上述入口重新打开
                </template>
              </text>
            </view>
          </view>
          <button
            class="task-btn"
            :class="
              myMiniProgramTaskClaimed
                ? 'task-btn--done'
                : launchFromMyMiniOrCollect
                ? 'task-btn--permanent'
                : 'task-btn--goto'
            "
            :disabled="myMiniProgramTaskClaimed || myMiniProgramTaskLoading"
            @click.stop="claimMyMiniProgramTask"
          >
            {{
              myMiniProgramTaskClaimed
                ? "✓ 已领取"
                : myMiniProgramTaskLoading
                ? "领取中…"
                : "领取"
            }}
          </button>
        </view>
        <view class="task-divider" />
        <view class="task-row">
          <view class="task-row-info">
            <text class="task-row-icon">⭐</text>
            <view class="task-row-main">
              <text class="task-row-name">给小程序评分</text>
              <text class="task-row-sub"
                >评分后可领 <text class="task-row-reward">+3次</text> 查看答案</text
              >
            </view>
          </view>
          <button
            class="task-btn"
            :class="rateTaskClaimed ? 'task-btn--done' : 'task-btn--permanent'"
            :disabled="rateTaskClaimed || rateTaskClaimLoading"
            @click.stop="claimRateTask"
          >
            {{
              rateTaskClaimed ? "✓ 已领取" : rateTaskClaimLoading ? "领取中…" : "去评分"
            }}
          </button>
        </view>
      </view>
    </view>

    <!-- 评分引导蒙版 -->
    <view v-if="showRateGuide" class="rate-guide-mask" @click="closeRateGuide">
      <view class="rate-guide-arrow" />
      <view class="rate-guide-tip">点击右上角「…」→ 给小程序评分</view>
      <view class="rate-guide-close">点击任意处关闭</view>
    </view>
  </view>
</template>

<script setup>
import { ref, computed, onBeforeUnmount } from "vue";
import { onShow, onShareAppMessage, onShareTimeline } from "@dcloudio/uni-app";
import { useNavBar } from "../../composables/useNavBar";
import { usePunShareReward } from "../../composables/usePunShareReward";
import { SHARE_SUCCESS_THRESHOLD_MS } from "../utils/punPlayShared";
import { api } from "../../utils/api";
import {
  createManagedRewardedVideoAd,
  destroyRewardedVideoAd,
  isRewardedVideoSupported,
  rewardedVideoFailToast,
  safeGetLaunchScene,
  safeOffRewardedVideoClose,
  showRewardedVideoAd,
} from "../../utils/rewardedVideoRunner";
import PunPageNavBar from "../../components/PunPageNavBar.vue";

const { statusBarHeight, navBarHeight, menuButtonHeight } = useNavBar();

const hintAnswerQuota = ref(0);
const hintAnswerShareDailyMax = ref(5);
const shareDailyClaimed = ref(0);
const shareClaimLoading = ref(false);
const dailyAnswerCount = ref(0);
const dailyAnswerRequired = ref(20);
const dailyNoonTaskClaimed = ref(false);
const dailyAdTaskCount = ref(0);
const dailyBattleCount = ref(0);
const dailyBattleRequired = ref(3);
const dailyBattleTaskClaimed = ref(false);
const avatarTaskClaimed = ref(false);
const nicknameTaskClaimed = ref(false);
const myMiniProgramTaskClaimed = ref(false);
const rateTaskClaimed = ref(false);
const showRateGuide = ref(false);
let rateIntentTs = 0;
const avatarTaskClaimLoading = ref(false);
const nicknameTaskClaimLoading = ref(false);
const myMiniProgramTaskLoading = ref(false);
const rateTaskClaimLoading = ref(false);
const launchFromMyMiniOrCollect = ref(false);
const dailyClaimLoading = ref(false);
const dailyAdTaskLoading = ref(false);
const dailyBattleTaskLoading = ref(false);

const canClaimDailyReward = computed(
  () => dailyAnswerCount.value >= dailyAnswerRequired.value
);
const shareTaskClaimedAll = computed(
  () =>
    hintAnswerShareDailyMax.value > 0 &&
    shareDailyClaimed.value >= hintAnswerShareDailyMax.value
);
const canClaimDailyBattleTask = computed(
  () =>
    !dailyBattleTaskClaimed.value && dailyBattleCount.value >= dailyBattleRequired.value
);

let shareLoadingTimer = null;
let mineDailyVideoAd = null;
let mineDailyVideoBusy = false;

const platform = (() => {
  try {
    return (uni.getAppBaseInfo?.() ?? uni.getSystemInfoSync()).uniPlatform || "";
  } catch {
    return "";
  }
})();
const isMiniProgram = platform.startsWith("mp-");

const { markShareIntent: markShareIntentTask, withShareReward } = usePunShareReward(
  hintAnswerQuota,
  {
    mode: "heuristic",
    shareSuccessThresholdMs: SHARE_SUCCESS_THRESHOLD_MS,
    showCancelToast: true,
    onClaimSuccess(payload) {
      if (shareLoadingTimer) {
        clearTimeout(shareLoadingTimer);
        shareLoadingTimer = null;
      }
      shareClaimLoading.value = false;
      const add = Number(payload && payload.added) > 0 ? Number(payload.added) : 1;
      const max = Math.max(0, Math.floor(hintAnswerShareDailyMax.value || 0));
      shareDailyClaimed.value = Math.max(
        0,
        Math.min(max, Math.floor(shareDailyClaimed.value + add))
      );
      refreshTaskData();
    },
  }
);

onShareAppMessage(() =>
  withShareReward({ title: "任务中心 · 谐音梗猜一猜", path: "/pages/index/index" })
);
onShareTimeline(() => withShareReward({ title: "任务中心 · 谐音梗猜一猜" }));

function readLaunchSceneSafe(getter) {
  try {
    if (typeof getter !== "function") return null;
    const opts = getter();
    const scene = opts?.scene;
    return scene != null && scene !== "" ? scene : null;
  } catch {
    return null;
  }
}

function isLaunchFromMyMiniOrCollectScene() {
  const scenes = [
    readLaunchSceneSafe(uni.getEnterOptionsSync),
    readLaunchSceneSafe(uni.getLaunchOptionsSync),
  ].filter(Boolean);
  if (scenes.length === 0) return false;
  const validScenes = [1103, 1104, 21003];
  for (const scene of scenes) {
    const n = Number(scene);
    if (!Number.isNaN(n) && validScenes.includes(n)) return true;
    if (String(scene) === "021003") return true;
  }
  return false;
}

function setRefIfChanged(refObj, next) {
  if (refObj.value !== next) {
    refObj.value = next;
  }
}

function normalizeTaskStatus(data) {
  const maxShare =
    typeof data?.hintAnswerShareDailyMax === "number" && data.hintAnswerShareDailyMax > 0
      ? Math.floor(data.hintAnswerShareDailyMax)
      : Math.floor(hintAnswerShareDailyMax.value || 5);
  return {
    hintAnswerQuota:
      typeof data?.hintAnswerQuota === "number" ? data.hintAnswerQuota : null,
    hintAnswerShareDailyMax: maxShare,
    hintAnswerShareDailyClaimed:
      typeof data?.hintAnswerShareDailyClaimed === "number" &&
      data.hintAnswerShareDailyClaimed >= 0
        ? Math.max(0, Math.min(maxShare, Math.floor(data.hintAnswerShareDailyClaimed)))
        : null,
    dailyAnswerRequired:
      typeof data?.dailyAnswerRequired === "number" && data.dailyAnswerRequired > 0
        ? Math.floor(data.dailyAnswerRequired)
        : null,
    dailyAnswerCount:
      typeof data?.dailyAnswerCount === "number" && data.dailyAnswerCount >= 0
        ? Math.max(0, Math.floor(data.dailyAnswerCount))
        : null,
    dailyNoonTaskClaimed:
      typeof data?.dailyNoonTaskClaimed !== "undefined"
        ? Number(data.dailyNoonTaskClaimed) > 0
        : null,
    dailyAdTaskCount:
      typeof data?.dailyAdTaskCount === "number" && data.dailyAdTaskCount >= 0
        ? Math.max(0, Math.floor(data.dailyAdTaskCount))
        : null,
    dailyBattleRequired:
      typeof data?.dailyBattleRequired === "number" && data.dailyBattleRequired > 0
        ? Math.max(1, Math.floor(data.dailyBattleRequired))
        : null,
    dailyBattleCount:
      typeof data?.dailyBattleCount === "number" && data.dailyBattleCount >= 0
        ? Math.max(0, Math.floor(data.dailyBattleCount))
        : null,
    dailyBattleTaskClaimed:
      typeof data?.dailyBattleTaskClaimed !== "undefined"
        ? Number(data.dailyBattleTaskClaimed) > 0
        : null,
    avatarTaskClaimed:
      typeof data?.avatarTaskClaimed !== "undefined"
        ? Number(data.avatarTaskClaimed) > 0
        : null,
    nicknameTaskClaimed:
      typeof data?.nicknameTaskClaimed !== "undefined"
        ? Number(data.nicknameTaskClaimed) > 0
        : null,
    myMiniProgramTaskClaimed:
      typeof data?.myMiniProgramTaskClaimed !== "undefined"
        ? Number(data.myMiniProgramTaskClaimed) > 0
        : null,
    rateTaskClaimed:
      typeof data?.rateTaskClaimed !== "undefined"
        ? Number(data.rateTaskClaimed) > 0
        : null,
  };
}

async function refreshTaskData() {
  try {
    const data = await api.getTaskStatus();
    if (!data) return;
    const normalized = normalizeTaskStatus(data);
    if (normalized.hintAnswerQuota !== null)
      setRefIfChanged(hintAnswerQuota, normalized.hintAnswerQuota);
    setRefIfChanged(hintAnswerShareDailyMax, normalized.hintAnswerShareDailyMax);
    if (normalized.hintAnswerShareDailyClaimed !== null) {
      setRefIfChanged(shareDailyClaimed, normalized.hintAnswerShareDailyClaimed);
    }
    if (normalized.dailyAnswerRequired !== null)
      setRefIfChanged(dailyAnswerRequired, normalized.dailyAnswerRequired);
    if (normalized.dailyAnswerCount !== null)
      setRefIfChanged(dailyAnswerCount, normalized.dailyAnswerCount);
    if (normalized.dailyNoonTaskClaimed !== null)
      setRefIfChanged(dailyNoonTaskClaimed, normalized.dailyNoonTaskClaimed);
    if (normalized.dailyAdTaskCount !== null)
      setRefIfChanged(dailyAdTaskCount, normalized.dailyAdTaskCount);
    if (normalized.dailyBattleRequired !== null)
      setRefIfChanged(dailyBattleRequired, normalized.dailyBattleRequired);
    if (normalized.dailyBattleCount !== null)
      setRefIfChanged(dailyBattleCount, normalized.dailyBattleCount);
    if (normalized.dailyBattleTaskClaimed !== null)
      setRefIfChanged(dailyBattleTaskClaimed, normalized.dailyBattleTaskClaimed);
    if (normalized.avatarTaskClaimed !== null)
      setRefIfChanged(avatarTaskClaimed, normalized.avatarTaskClaimed);
    if (normalized.nicknameTaskClaimed !== null)
      setRefIfChanged(nicknameTaskClaimed, normalized.nicknameTaskClaimed);
    if (normalized.myMiniProgramTaskClaimed !== null) {
      setRefIfChanged(myMiniProgramTaskClaimed, normalized.myMiniProgramTaskClaimed);
    }
    if (normalized.rateTaskClaimed !== null)
      setRefIfChanged(rateTaskClaimed, normalized.rateTaskClaimed);
  } catch {}
}

onShow(() => {
  launchFromMyMiniOrCollect.value = isLaunchFromMyMiniOrCollectScene();
  refreshTaskData();
  // 评分返回后判定：离开超过 2 秒视为已评分
  if (rateIntentTs > 0 && !rateTaskClaimed.value) {
    const elapsed = Date.now() - rateIntentTs;
    rateIntentTs = 0;
    if (elapsed >= SHARE_SUCCESS_THRESHOLD_MS) {
      doClaimRateReward();
    } else {
      uni.showToast({ title: "请先完成评分再领取", icon: "none" });
    }
  }
});

function back() {
  uni.navigateBack({ delta: 1, fail: () => uni.reLaunch({ url: "/pages/index/index" }) });
}

function goHome() {
  uni.reLaunch({ url: "/pages/index/index" });
}

// ── 广告 ────────────────────────────────────────────────
function invalidateMineDailyVideoAd() {
  mineDailyVideoAd = null;
  mineDailyVideoBusy = false;
}

function ensureMineDailyVideoAd() {
  if (mineDailyVideoAd && typeof mineDailyVideoAd.show === "function") {
    return mineDailyVideoAd;
  }
  mineDailyVideoAd = null;
  mineDailyVideoAd = createManagedRewardedVideoAd({ onInvalid: invalidateMineDailyVideoAd });
  return mineDailyVideoAd;
}

function watchDailyTaskAd() {
  if (mineDailyVideoBusy) return Promise.resolve(false);
  if (!isRewardedVideoSupported()) {
    uni.showToast({ title: "当前环境不支持激励视频", icon: "none" });
    return Promise.resolve(false);
  }
  const ad = ensureMineDailyVideoAd();
  if (!ad) {
    uni.showToast({ title: "当前环境不支持激励视频", icon: "none" });
    return Promise.resolve(false);
  }
  mineDailyVideoBusy = true;
  return new Promise((resolve) => {
    let settled = false;
    const finish = (ok) => {
      if (settled) return;
      settled = true;
      mineDailyVideoBusy = false;
      resolve(ok);
    };
    const onClose = (res) => {
      safeOffRewardedVideoClose(ad, onClose);
      finish(!!(res && res.isEnded));
    };
    if (typeof ad.onClose !== "function") {
      rewardedVideoFailToast(new Error("广告实例无效"));
      finish(false);
      return;
    }
    ad.onClose(onClose);
    showRewardedVideoAd(ad)
      .catch((err) => {
        console.warn("[rewardedVideo] show failed", err);
        safeOffRewardedVideoClose(ad, onClose);
        invalidateMineDailyVideoAd();
        rewardedVideoFailToast(err);
        finish(false);
      });
  });
}

// ── 领取函数 ─────────────────────────────────────────────
async function claimDailyNoonReward() {
  if (dailyClaimLoading.value || dailyNoonTaskClaimed.value) return;
  dailyClaimLoading.value = true;
  try {
    const data = await api.claimReward({ type: "daily_noon_hint_5", add: 5 });
    if (typeof data.hintAnswerQuota === "number")
      hintAnswerQuota.value = data.hintAnswerQuota;
    dailyNoonTaskClaimed.value = true;
    uni.showToast({ title: `领取成功 +${data.added || 5}`, icon: "none" });
    refreshTaskData();
  } catch (e) {
    uni.showToast({ title: e.message || "领取失败", icon: "none" });
  } finally {
    dailyClaimLoading.value = false;
  }
}

function onDailyRewardAction() {
  if (dailyNoonTaskClaimed.value) return;
  if (!canClaimDailyReward.value) {
    uni.reLaunch({ url: "/pages/index/index" });
    return;
  }
  claimDailyNoonReward();
}

async function claimDailyAdTask() {
  if (dailyAdTaskLoading.value) return;
  if (!isMiniProgram) {
    uni.showToast({ title: "仅小程序支持看广告领取", icon: "none" });
    return;
  }
  if (!ensureMineDailyVideoAd()) {
    uni.showToast({ title: "当前平台暂不支持激励视频", icon: "none" });
    return;
  }
  dailyAdTaskLoading.value = true;
  try {
    const watched = await watchDailyTaskAd();
    if (!watched) {
      uni.showToast({ title: "请完整观看广告后领取", icon: "none" });
      return;
    }
    const data = await api.claimReward({ type: "daily_watch_ad_hint_1", add: 1 });
    if (typeof data.hintAnswerQuota === "number")
      hintAnswerQuota.value = data.hintAnswerQuota;
    dailyAdTaskCount.value = Math.max(
      0,
      dailyAdTaskCount.value + (Number(data.added) > 0 ? Number(data.added) : 1)
    );
    uni.showToast({ title: `领取成功 +${data.added || 1}`, icon: "none" });
    refreshTaskData();
  } catch (e) {
    uni.showToast({ title: e.message || "领取失败", icon: "none" });
  } finally {
    dailyAdTaskLoading.value = false;
  }
}

async function claimDailyBattleTask() {
  if (dailyBattleTaskLoading.value || dailyBattleTaskClaimed.value) return;
  dailyBattleTaskLoading.value = true;
  try {
    const data = await api.claimReward({ type: "daily_battle_3_hint_3", add: 3 });
    if (typeof data.hintAnswerQuota === "number")
      hintAnswerQuota.value = data.hintAnswerQuota;
    dailyBattleTaskClaimed.value = true;
    uni.showToast({ title: `领取成功 +${data.added || 3}`, icon: "none" });
    refreshTaskData();
  } catch (e) {
    uni.showToast({ title: e.message || "领取失败", icon: "none" });
  } finally {
    dailyBattleTaskLoading.value = false;
  }
}

function onDailyBattleTaskAction() {
  if (dailyBattleTaskClaimed.value) return;
  if (!canClaimDailyBattleTask.value) {
    uni.navigateTo({ url: "/pages-sub/battleRoom/battleRoom" });
    return;
  }
  claimDailyBattleTask();
}

async function claimAvatarTask() {
  if (avatarTaskClaimLoading.value || avatarTaskClaimed.value) return;
  avatarTaskClaimLoading.value = true;
  try {
    const data = await api.claimReward({ type: "permanent_set_avatar" });
    if (typeof data.hintAnswerQuota === "number")
      hintAnswerQuota.value = data.hintAnswerQuota;
    avatarTaskClaimed.value = true;
    uni.showToast({ title: `领取成功 +${data.added || 3}`, icon: "none" });
  } catch (e) {
    uni.showToast({ title: e.message || "领取失败", icon: "none" });
  } finally {
    avatarTaskClaimLoading.value = false;
  }
}

async function claimNicknameTask() {
  if (nicknameTaskClaimLoading.value || nicknameTaskClaimed.value) return;
  nicknameTaskClaimLoading.value = true;
  try {
    const data = await api.claimReward({ type: "permanent_set_nickname" });
    if (typeof data.hintAnswerQuota === "number")
      hintAnswerQuota.value = data.hintAnswerQuota;
    nicknameTaskClaimed.value = true;
    uni.showToast({ title: `领取成功 +${data.added || 3}`, icon: "none" });
  } catch (e) {
    uni.showToast({ title: e.message || "领取失败", icon: "none" });
  } finally {
    nicknameTaskClaimLoading.value = false;
  }
}

async function claimMyMiniProgramTask() {
  if (myMiniProgramTaskLoading.value || myMiniProgramTaskClaimed.value) return;
  launchFromMyMiniOrCollect.value = isLaunchFromMyMiniOrCollectScene();
  if (!launchFromMyMiniOrCollect.value) {
    uni.showToast({
      title: "请先添加到「我的小程序/收藏」，关闭后从该入口重新进入",
      icon: "none",
    });
    return;
  }
  myMiniProgramTaskLoading.value = true;
  try {
    const launchScene = safeGetLaunchScene();
    const data = await api.claimReward({
      type: "permanent_my_mini_program_hint_3",
      launchScene,
    });
    if (typeof data.hintAnswerQuota === "number")
      hintAnswerQuota.value = data.hintAnswerQuota;
    myMiniProgramTaskClaimed.value = true;
    uni.showToast({ title: `领取成功 +${data.added || 3}`, icon: "none" });
    refreshTaskData();
  } catch (e) {
    uni.showToast({ title: e.message || "领取失败", icon: "none" });
  } finally {
    myMiniProgramTaskLoading.value = false;
  }
}

function claimRateTask() {
  if (rateTaskClaimLoading.value || rateTaskClaimed.value) return;
  // 显示引导蒙版，记录意图时间戳
  showRateGuide.value = true;
  rateIntentTs = Date.now();
}

function closeRateGuide() {
  showRateGuide.value = false;
}

async function doClaimRateReward() {
  if (rateTaskClaimLoading.value || rateTaskClaimed.value) return;
  rateTaskClaimLoading.value = true;
  try {
    const data = await api.claimReward({ type: "permanent_rate_app" });
    if (typeof data.hintAnswerQuota === "number")
      hintAnswerQuota.value = data.hintAnswerQuota;
    rateTaskClaimed.value = true;
    uni.showToast({ title: `领取成功 +${data.added || 3}`, icon: "none" });
    refreshTaskData();
  } catch (e) {
    uni.showToast({ title: e.message || "领取失败", icon: "none" });
  } finally {
    rateTaskClaimLoading.value = false;
  }
}

function onShareTaskIntent() {
  if (!isMiniProgram) {
    uni.showToast({ title: "仅小程序支持分享领奖", icon: "none" });
    return;
  }
  if (shareTaskClaimedAll.value) return;
  shareClaimLoading.value = true;
  if (shareLoadingTimer) {
    clearTimeout(shareLoadingTimer);
    shareLoadingTimer = null;
  }
  shareLoadingTimer = setTimeout(() => {
    shareLoadingTimer = null;
    shareClaimLoading.value = false;
  }, 6000);
  markShareIntentTask();
}

onBeforeUnmount(() => {
  if (shareLoadingTimer) {
    clearTimeout(shareLoadingTimer);
    shareLoadingTimer = null;
  }
  shareClaimLoading.value = false;
  destroyRewardedVideoAd(mineDailyVideoAd);
  mineDailyVideoAd = null;
  mineDailyVideoBusy = false;
});
</script>

<style lang="scss" scoped>
@use '../../styles/page-theme.scss' as *;

.page {
  min-height: 100vh;
  position: relative;
  padding: 0 40rpx 48rpx;
  padding-bottom: calc(48rpx + env(safe-area-inset-bottom));
  box-sizing: border-box;
  @include pt-page-background;
}

.section {
  position: relative;
  z-index: 2;
  width: 100%;
  margin: 20rpx auto 0;
}

.nav-home-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 60rpx;
  height: 60rpx;
  margin-right: 16rpx;
}

.nav-home-img {
  width: 40rpx;
  height: 40rpx;
}

.quota-banner {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 24rpx 28rpx;
  margin-bottom: 20rpx;
  background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
  border-radius: 24rpx;
  border: 2rpx solid rgba(52, 211, 153, 0.3);
  box-shadow: 0 4rpx 14rpx rgba(52, 211, 153, 0.18);
}

.quota-banner-label {
  font-size: 26rpx;
  font-weight: 700;
  color: #065f46;
}

.quota-banner-value-row {
  display: flex;
  align-items: baseline;
  gap: 4rpx;
}

.quota-banner-num {
  font-size: 52rpx;
  font-weight: 900;
  color: #059669;
  line-height: 1;
}

.quota-banner-unit {
  font-size: 24rpx;
  font-weight: 700;
  color: #059669;
}

.task-section {
  margin-bottom: 20rpx;
  background: rgba(255, 255, 255, 0.82);
  border-radius: 24rpx;
  border: 2rpx solid $pt-border;
  box-shadow: 0 6rpx 18rpx $pt-shadow-soft;
  overflow: hidden;
}

.task-section-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 18rpx 24rpx 16rpx;
}

.task-section-header--permanent {
  background: linear-gradient(135deg, #f5f0ff 0%, #ede8ff 100%);
  border-bottom: 1rpx solid rgba(167, 139, 250, 0.25);
}

.task-section-header--daily {
  background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%);
  border-bottom: 1rpx solid rgba(251, 191, 36, 0.25);
}

.task-section-header-left {
  display: flex;
  align-items: center;
  gap: 10rpx;
}

.task-section-icon {
  font-size: 30rpx;
  line-height: 1;
}

.task-section-title {
  font-size: 26rpx;
  font-weight: 800;
  letter-spacing: 0.04em;
  color: $pt-text;
}

.task-section-badge {
  font-size: 20rpx;
  font-weight: 600;
  color: $pt-muted;
  background: rgba(255, 255, 255, 0.7);
  padding: 4rpx 14rpx;
  border-radius: 100rpx;
}

.task-row {
  display: flex;
  align-items: center;
  padding: 22rpx 24rpx;
  gap: 16rpx;
}

.task-row-info {
  display: flex;
  align-items: flex-start;
  gap: 16rpx;
  flex: 1;
  min-width: 0;
}

.task-row-icon {
  font-size: 36rpx;
  line-height: 1;
  margin-top: 2rpx;
  flex-shrink: 0;
}

.task-row-main {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 6rpx;
}

.task-row-name {
  font-size: 27rpx;
  font-weight: 700;
  color: $pt-text;
  line-height: 1.3;
}
.task-row-sub {
  font-size: 21rpx;
  color: $pt-muted;
  line-height: 1.45;
}
.task-row-reward {
  font-size: 21rpx;
  font-weight: 700;
  color: #d97706;
}

.task-row-progress-wrap {
  display: flex;
  flex-direction: column;
  gap: 6rpx;
}

.task-row-progress-bar {
  width: 100%;
  height: 6rpx;
  border-radius: 6rpx;
  background: rgba(0, 0, 0, 0.08);
  overflow: hidden;
}

.task-row-progress-fill {
  height: 100%;
  border-radius: 6rpx;
  background: linear-gradient(90deg, #86efac 0%, #22c55e 100%);
  transition: width 0.3s ease;
}

.task-divider {
  height: 1rpx;
  margin: 0 24rpx;
  background: rgba(0, 0, 0, 0.05);
}

.task-btn {
  flex-shrink: 0;
  min-width: 120rpx;
  height: 58rpx;
  line-height: 58rpx;
  border-radius: 100rpx;
  border: none;
  font-size: 23rpx;
  font-weight: 700;
  padding: 0 20rpx;
  text-align: center;
}

.task-btn::after {
  border: none;
}
.task-btn--permanent {
  background: linear-gradient(135deg, #c4b5fd 0%, #a78bfa 100%);
  color: #4c1d95;
}
.task-btn--daily {
  background: linear-gradient(135deg, #fde68a 0%, #f59e0b 100%);
  color: #78350f;
}
.task-btn--share {
  background: linear-gradient(135deg, #bae6fd 0%, #38bdf8 100%);
  color: #0c4a6e;
}
.task-btn--goto {
  background: rgba(0, 0, 0, 0.06);
  color: $pt-muted;
}
.task-btn--done {
  background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
  color: #065f46;
}

.task-btn[disabled].task-btn--done {
  background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%) !important;
  color: #065f46 !important;
}

.task-btn[disabled]:not(.task-btn--done) {
  background: linear-gradient(135deg, #e5e7eb 0%, #d1d5db 100%) !important;
  color: #9ca3af !important;
}

/* 评分引导蒙版 */
.rate-guide-mask {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  z-index: 9999;
  background: rgba(0, 0, 0, 0.75);
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  padding-top: 20%;
  padding-right: 40rpx;
}
.rate-guide-arrow {
  width: 0;
  height: 0;
  border-left: 20rpx solid transparent;
  border-right: 20rpx solid transparent;
  border-bottom: 30rpx solid #fff;
  margin-right: 30rpx;
  margin-bottom: 16rpx;
}
.rate-guide-tip {
  color: #fff;
  font-size: 32rpx;
  font-weight: 700;
  text-align: right;
  margin-right: 0;
  line-height: 1.6;
}
.rate-guide-close {
  color: rgba(255, 255, 255, 0.6);
  font-size: 24rpx;
  margin-top: 40rpx;
  text-align: center;
  width: 100%;
  position: absolute;
  bottom: 120rpx;
  left: 0;
}
</style>
