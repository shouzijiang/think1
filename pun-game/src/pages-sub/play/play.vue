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
      @left-click="back"
    >
      <template #title>
        <text class="nav-title">梗图填词 · 第{{ level }}关</text>
      </template>
    </PunPageNavBar>

    <view class="card">
      <view class="card-inner">
        <PunGlobalWatermark />
        <view class="img-box">
          <image
            v-if="puzzle.imageUrl"
            class="puzzle-img"
            :src="puzzle.imageUrl"
            mode="aspectFill"
            @load="imageReady = true"
            @error="imageReady = true"
          />
          <view v-if="!imageReady" class="puzzle-loading">
            <text v-if="puzzle.imageUrl || loading">加载中...</text>
            <text v-else>暂无配图</text>
          </view>
        </view>
        <text class="puzzle-hint">{{ puzzle.hintText }}</text>
        <view class="skip-entry" @click="onSkipLevel">跳关</view>
        <view class="report-entry" @click="goFeedback">报错</view>
      </view>
    </view>

    <view class="answer-block">
      <view class="answer-row answer-row--divider">
        <view class="answer-left">
          <view class="answer-slots">
            <view
              v-for="i in answerLen"
              :key="i"
              :class="[
                'slot',
                { 'slot-error': isSlotError(i - 1), 'slot-shake': slotShake },
              ]"
              @click="removeAt(i - 1)"
            >
              {{ answerChars[i - 1] || "" }}
            </view>
          </view>
        </view>
      </view>

      <PunPlayHintShareBar
        :show-hint="true"
        :hint-loading="hintLoading"
        :hint-answer-quota="hintAnswerQuota"
        :hint-locked="loading"
        @hint="onRevealHint"
        @help="help"
        @share-intent="markShareIntent"
      />
    </view>

    <!-- <view class="stuck-tip">
      <text>若通过后没进入下一关，请点击左上角重新游戏</text>
    </view> -->

    <view class="chars-wrap">
      <view
        v-for="(c, idx) in chars"
        :key="idx"
        :class="['char-btn', { 'char-btn-used': isCharUsed(idx) }]"
        :hover-class="isCharUsed(idx) ? 'none' : 'char-btn--hover'"
        :hover-start-time="20"
        :hover-stay-time="100"
        @click="pickChar(c, idx)"
      >
        {{ c }}
      </view>
    </view>

    <PunPassSuccessOverlay
      :show="showSuccess"
      variant="rich"
      :sub-text="passSuccessExplain"
      :tap-anywhere="true"
      @action="confirmPassSuccess"
    />
    <PunHintOverlay
      :show="hintOverlayVisible"
      :hint-text="hintOverlayText"
      :step="hintOverlayStep"
      :max-steps="hintOverlayMaxSteps"
      :is-complete="hintOverlayComplete"
      @close="hintOverlayVisible = false"
    />
  </view>
</template>

<script setup>
import { ref, computed } from "vue";
import {
  onLoad,
  onShow,
  onHide,
  onShareAppMessage,
  onShareTimeline,
} from "@dcloudio/uni-app";
import { getLevelPuzzle } from "../../data/levels";
import { api } from "../../utils/api";
import { useNavBar } from "../../composables/useNavBar";
import PunPageNavBar from "../../components/PunPageNavBar.vue";
import PunPlayHintShareBar from "../../components/PunPlayHintShareBar.vue";
import PunPassSuccessOverlay from "../../components/PunPassSuccessOverlay.vue";
import PunHintOverlay from "../../components/PunHintOverlay.vue";
import { usePunPassSuccess } from "../composables/usePunPassSuccess";
import { usePunShareReward } from "../../composables/usePunShareReward";
import { usePunRewardedVideoHint } from "../composables/usePunRewardedVideoHint";
import { usePunSkipLevel } from "../composables/usePunSkipLevel";
import { playBgmPlay, stopBgm } from "../../utils/gameAudio";
import { resolvePassExplain } from "../utils/punPassExplain";
import {
  punGetCachedHint,
  punIsSlotError,
  punScheduleWrongAnswerReset,
  punRevealHintWithModal,
  punToastRevealHintAfterError,
  SHARE_SUCCESS_THRESHOLD_MS,
} from "../utils/punPlayShared";

const { statusBarHeight, navBarHeight, menuButtonHeight } = useNavBar();

const level = ref(1);
const answerLen = ref(3);
const answerChars = ref([]);
const chars = ref([]);
const puzzle = ref({
  imageUrl: "",
  hintText: "",
});
const loading = ref(true);
const imageReady = ref(false);
const submitting = ref(false);

const feedback = ref([]);
const slotShake = ref(false);
const { showSuccess, runPassSuccess, confirmPassSuccess } = usePunPassSuccess();
const passSuccessExplain = ref("");
const hintLoading = ref(false);
const skipLoading = ref(false);
/** 揭字剩余次数（/pun/level/progress 与 reveal-hint 返回） */
const hintAnswerQuota = ref(0);
const hintOverlayVisible = ref(false);
const hintOverlayText = ref("");
const hintOverlayStep = ref(0);
const hintOverlayMaxSteps = ref(0);
const hintOverlayComplete = ref(false);
const { markShareIntent, withShareReward } = usePunShareReward(hintAnswerQuota, {
  mode: "heuristic",
  shareSuccessThresholdMs: SHARE_SUCCESS_THRESHOLD_MS,
  showCancelToast: true,
});
const { tryWatchAdForHintQuota } = usePunRewardedVideoHint(hintAnswerQuota);
/** 当前已选入答案槽的字在 chars 中的下标，与 answerChars 一一对应 */
const pickedIndices = ref([]);
const pickedIndexSet = computed(() => new Set(pickedIndices.value));

function isCharUsed(idx) {
  return pickedIndexSet.value.has(idx);
}

function isSlotError(index) {
  return punIsSlotError(feedback.value, index);
}

onShow(() => {
  playBgmPlay();
});

onHide(() => {
  stopBgm();
});

onLoad((opts) => {
  let lv = 1;
  if (opts && opts.level != null && String(opts.level).trim() !== "") {
    const n = parseInt(String(opts.level), 10);
    if (Number.isFinite(n) && n >= 1) {
      lv = n;
    } else {
      uni.showToast({ title: "关卡号无效，已打开第1关", icon: "none" });
    }
  }
  level.value = lv;
  loading.value = true;
  imageReady.value = false;
  getLevelPuzzle(level.value)
    .then((data) => {
      answerLen.value = data.answerLength;
      chars.value = data.wordArray.length ? data.wordArray : [];
      puzzle.value = {
        imageUrl: data.imageUrl || "",
        hintText: data.hintText || "",
      };
      answerChars.value = [];
      pickedIndices.value = [];
      passSuccessExplain.value = "";
      loading.value = false;
    })
    .catch(() => {
      loading.value = false;
    });
  api
    .getLevelProgress({ gameTier: "beginner" })
    .then((prog) => {
      if (typeof prog.hintAnswerQuota === "number") {
        hintAnswerQuota.value = prog.hintAnswerQuota;
      }
    })
    .catch(() => {});
});

function pickChar(c, idx) {
  if (answerChars.value.length >= answerLen.value) return;
  if (isCharUsed(idx)) return;
  answerChars.value.push(c);
  pickedIndices.value.push(idx);
  if (answerChars.value.length === answerLen.value) {
    checkAnswer();
  }
}

function removeAt(i) {
  if (i < 0 || i >= answerChars.value.length) return;
  answerChars.value.splice(i, 1);
  pickedIndices.value.splice(i, 1);
}

async function checkAnswer() {
  if (submitting.value) return;
  const userAnswer = answerChars.value.slice();
  if (userAnswer.length !== answerLen.value) return;
  submitting.value = true;
  feedback.value = [];
  try {
    const data = await api.submitAnswer(level.value, userAnswer);
    if (data.isCorrect) {
      const rawNextLevel = data && data.nextLevel != null ? Number(data.nextLevel) : null;
      const rawTotalLevels =
        data && data.totalLevels != null ? Number(data.totalLevels) : NaN;
      const submitResult = {
        nextLevel: Number.isFinite(rawNextLevel) ? rawNextLevel : null,
        totalLevels:
          Number.isFinite(rawTotalLevels) && rawTotalLevels > 0 ? rawTotalLevels : NaN,
      };
      runPassSuccess({
        durationMs: 1500,
        manualClose: true,
        afterPrepare: async () => {
          applyPassExplain(data.passExplain);
          return submitResult;
        },
        onAfter: (prep) => {
          const goHomeAllClear = () => {
            uni.showToast({
              title: "恭喜通关全部梗图填词！",
              icon: "none",
              duration: 2000,
            });
            setTimeout(() => {
              uni.reLaunch({ url: "/pages/index/index" });
            }, 1600);
          };
          const totalCap =
            prep &&
            typeof prep === "object" &&
            prep !== null &&
            Number.isFinite(prep.totalLevels) &&
            prep.totalLevels > 0
              ? prep.totalLevels
              : NaN;
          const nextLevel =
            prep && typeof prep === "object" && prep !== null && "nextLevel" in prep
              ? prep.nextLevel
              : prep;
          // 已全部通关时 afterPrepare 返回 nextLevel: null；切勿 Number(null)===0 误跳 ?level=0
          if (nextLevel == null) {
            goHomeAllClear();
            return;
          }
          const targetLevel = Number(nextLevel);
          if (!Number.isFinite(targetLevel) || targetLevel < 1) {
            goHomeAllClear();
            return;
          }
          if (Number.isFinite(totalCap) && targetLevel > totalCap) {
            goHomeAllClear();
            return;
          }
          // 使用 redirectTo 替换当前页，避免连续 navigateTo 堆满页面栈（微信约 10 层上限）
          uni.redirectTo({ url: `/pages-sub/play/play?level=${targetLevel}` });
        },
      });
      return;
    }
    feedback.value = data.feedback || [];
    punScheduleWrongAnswerReset(slotShake, () => {
      answerChars.value = [];
      pickedIndices.value = [];
      feedback.value = [];
    });
  } catch (e) {
    uni.showToast({ title: e.message || "提交失败", icon: "none" });
  } finally {
    submitting.value = false;
  }
}

function applyPassExplain(apiExplain) {
  passSuccessExplain.value = resolvePassExplain(apiExplain);
}

async function onRevealHint() {
  if (hintLoading.value || loading.value) return;
  const hintPayload = { level: level.value, gameTier: "beginner" };
  const cachedHint = punGetCachedHint(hintPayload);
  if (cachedHint && cachedHint.isComplete) {
    hintOverlayText.value = String(cachedHint.hintText || "");
    hintOverlayStep.value = Number(cachedHint.step || 0);
    hintOverlayMaxSteps.value = Number(cachedHint.maxSteps || 0);
    hintOverlayComplete.value = true;
    hintOverlayVisible.value = true;
    return;
  }
  let afterRewardVideo = false;
  if (hintAnswerQuota.value <= 0) {
    afterRewardVideo = true;
    hintLoading.value = true;
    try {
      const ok = await tryWatchAdForHintQuota();
      if (!ok) {
        uni.showToast({ title: "次数不足，请前往首页获取更多", icon: "none" });
        return;
      }
    } finally {
      hintLoading.value = false;
    }
    if (hintAnswerQuota.value <= 0) {
      uni.showToast({ title: "次数不足", icon: "none" });
      return;
    }
  }
  hintLoading.value = true;
  try {
    const res = await punRevealHintWithModal(hintPayload);
    if (typeof res.hintAnswerQuota === "number") {
      hintAnswerQuota.value = res.hintAnswerQuota;
    }
    hintOverlayText.value = String(res.hintText || "");
    hintOverlayStep.value = Number(res.step || 0);
    hintOverlayMaxSteps.value = Number(res.maxSteps || 0);
    hintOverlayComplete.value = !!res.isComplete;
    hintOverlayVisible.value = true;
  } catch (e) {
    punToastRevealHintAfterError(e, afterRewardVideo);
  } finally {
    hintLoading.value = false;
  }
}

function back() {
  uni.reLaunch({ url: "/pages/index/index" });
}

function goFeedback() {
  const title = `梗图填词 · 第${level.value}关`;
  const query = `type=bug&passedLevels=${encodeURIComponent(
    String(level.value)
  )}&content=${encodeURIComponent(title)}&contentFocus=1`;
  uni.navigateTo({ url: `/pages-sub/feedback/feedback?${query}` });
}

const { onSkipLevel } = usePunSkipLevel({
  mode: "beginner",
  levelRef: level,
  hintAnswerQuotaRef: hintAnswerQuota,
  skipLoadingRef: skipLoading,
  loadingRefs: [loading, submitting, hintLoading],
  isValidNextLevel: (n) => Number.isFinite(n) && n > 0,
  toNextUrl: (n) => `/pages-sub/play/play?level=${n}`,
});

// 非小程序端点击「求助」时提示（小程序端由 open-type="share" 唤起分享）
function help() {
  const p = (uni.getAppBaseInfo?.() ?? uni.getSystemInfoSync()).uniPlatform || "";
  if (!(p === "mp-weixin" || p === "mp-toutiao")) {
    uni.showToast({ title: "分享给好友一起猜～", icon: "none" });
  }
}

// 微信小程序分享：好友打开后进入当前关卡
onShareAppMessage(() => {
  return withShareReward({
    title: `第${level.value}关求助，快来帮我猜谐音梗！`,
    path: `/pages-sub/play/play?level=${level.value}`,
  });
});

onShareTimeline(() => {
  const img =
    puzzle.value.imageUrl && String(puzzle.value.imageUrl).startsWith("http")
      ? puzzle.value.imageUrl
      : "";
  return {
    title: `第${level.value}关求助，快来帮我猜谐音梗！`,
    query: `level=${level.value}`,
    ...(img ? { imageUrl: img } : {}),
  };
});
</script>

<style lang="scss" scoped>
@use '../../styles/page-theme.scss' as *;

.page {
  min-height: 100vh;
  position: relative;
  padding-bottom: 48rpx;
  padding-left: 40rpx;
  padding-right: 40rpx;
  box-sizing: border-box;
  @include pt-page-background;
}

.btn-help {
  position: absolute; /* 绝对定位到右侧，因为微信胶囊存在，其实这块区域容易被挡住，看需求可以留着或隐藏 */
  right: 0;
  margin: 0;
  padding: 18rpx 28rpx;
  border: none;
  border-radius: 24rpx;
  background: rgba(255, 255, 255, 0.92);
  color: #5a6d7a;
  font-size: inherit;
  line-height: inherit;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8rpx;
  box-shadow: 0 4rpx 14rpx rgba(169, 201, 238, 0.2);
  border: 2rpx solid rgba(169, 201, 238, 0.55);
}
.btn-help::after {
  border: none;
}
.card {
  position: relative;
  z-index: 2;
  margin-bottom: 20rpx;
  border-radius: 24rpx;
  overflow: hidden;
  box-shadow: 0 8rpx 28rpx rgba(169, 201, 238, 0.18), 0 2rpx 8rpx rgba(0, 0, 0, 0.04);
  background: rgba(255, 255, 255, 0.95);
  border: 2rpx solid rgba(169, 201, 238, 0.45);
}
.card-inner {
  position: relative;
  min-height: 400rpx;
  display: flex;
  flex-direction: column;
  align-items: center;
  border-radius: 24rpx;
}
.report-entry {
  @include pt-report-entry;
}
.skip-entry {
  @include pt-skip-entry;
}
.img-box {
  position: relative;
  width: 100%;
  border-radius: 16rpx;
  overflow: hidden;
}
.puzzle-img {
  width: 110%;
  height: 750rpx;
  border-radius: 16rpx;
}
.puzzle-loading {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  min-height: 420rpx;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 28rpx;
  color: #8eadcf;
  background: rgba(240, 244, 248, 0.95);
  border-radius: 16rpx;
}
.puzzle-hint {
  margin-top: 24rpx;
  padding: 0 24rpx 28rpx;
  font-size: 32rpx;
  color: #5a6d7a;
  font-weight: 500;
  text-align: center;
}

/* 答题区 + 底部操作条：与 playMid / playXhs 同一结构 */
.answer-block {
  @include pt-answer-block;
}
.answer-row {
  @include pt-answer-row;
}
.answer-row--divider {
  border-bottom: 1rpx solid rgba(160, 190, 220, 0.35);
}
.answer-left {
  @include pt-answer-left;
}
.stuck-tip {
  position: relative;
  z-index: 2;
  text-align: center;
  font-size: 24rpx;
  color: #8eadcf;
  margin-top: -16rpx;
  margin-bottom: 32rpx;
}
.answer-slots {
  display: flex;
  flex-direction: row;
  justify-content: center;
  align-items: center;
  gap: 18rpx;
}

.answer-btn-placeholder {
  width: 100%;
  min-height: 86rpx;
  border-radius: 20rpx;
  background: rgba(255, 255, 255, 0.15);
  border: 2rpx dashed rgba(180, 160, 140, 0.25);
}
.slot {
  width: 76rpx;
  height: 76rpx;
  border: 2rpx dashed rgba(169, 201, 238, 0.75);
  border-radius: 20rpx;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 36rpx;
  font-weight: 600;
  color: #5a6d7a;
  background: rgba(255, 255, 255, 0.9);
}
.slot-error {
  @include pt-slot-error;
}
.slot-shake {
  @include pt-slot-shake;
}
@include pt-keyframes-slot-shake;

.chars-wrap {
  position: relative;
  z-index: 2;
  display: flex;
  flex-wrap: wrap;
  gap: 18rpx;
  justify-content: center;
}
.char-btn {
  width: 88rpx;
  height: 88rpx;
  border-radius: 50%;
  background: linear-gradient(145deg, #a8e6a2 0%, #91d58b 55%, #7ec876 100%);
  color: #fff;
  font-size: 36rpx;
  font-weight: 600;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 6rpx 20rpx rgba(111, 184, 104, 0.35),
    inset 0 2rpx 0 rgba(255, 255, 255, 0.35);
  border: 2rpx solid rgba(255, 255, 255, 0.35);
}
.char-btn--hover {
  transform: scale(0.96);
}
.char-btn-used {
  background: rgba(234, 246, 249, 0.85);
  color: #8eadcf;
  box-shadow: none;
  border: 2rpx solid rgba(169, 201, 238, 0.45);
}
</style>
