<template>
  <view class="page page--xhs">
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
        <text class="nav-title">小红书专辑 · 第{{ level }}关</text>
      </template>
    </PunPageNavBar>

    <view class="card card--xhs">
      <view v-if="puzzle.keywordHint && !hideKeywordHint" class="keyword-tab">
        <text class="keyword-tab-text">提示：{{ puzzle.keywordHint }}</text>
      </view>
      <view class="author-tag" v-if="puzzle.author">
        <text class="author-text">作者：{{ puzzle.author }}</text>
      </view>
      <view class="card-inner">
        <PunGlobalWatermark />
        <PunWrongAnswerFloat :items="wrongFloatItems" />
        <view class="img-box">
          <image
            v-if="puzzle.imageUrlTop"
            class="main-img"
            :src="puzzle.imageUrlTop"
            mode="aspectFill"
            @load="imageReady = true"
            @error="imageReady = true"
          />
          <view v-if="!imageReady" class="img-placeholder">
            <text v-if="puzzle.imageUrlTop || loading">加载中...</text>
            <text v-else>暂无配图</text>
          </view>
        </view>
        <view class="skip-entry" @click="onSkipLevel">跳关</view>
        <view class="report-entry" @click="goFeedback">报错</view>
      </view>
    </view>

    <view class="answer-block">
      <view class="answer-row">
        <view class="answer-left">
          <input
            ref="xhsInputRef"
            class="answer-input-cover"
            type="text"
            :value="answerInputValue"
            @input="onAnswerInput"
            confirm-type="done"
            @focus="onInputFocus"
            @blur="onInputBlur"
            @click.stop="focusInput()"
          />
          <view class="answer-slots">
            <view
              v-for="i in answerLen"
              :key="i"
              :class="[
                'slot',
                { 'slot-error': isSlotError(i - 1), 'slot-shake': slotShake },
              ]"
            >
              <text v-if="answerChars[i - 1]" class="slot-char">{{
                answerChars[i - 1]
              }}</text>
              <view v-else-if="caretIndex === i - 1" class="slot-caret" />
            </view>
          </view>
        </view>
      </view>

      <PunPlayHintShareBar
        :hint-loading="hintLoading"
        :hint-answer-quota="hintAnswerQuota"
        :hint-locked="loading"
        @hint="onRevealHint"
        @help="help"
        @share-intent="markShareIntent"
      />
    </view>

    <PunPassSuccessOverlay
      :show="showSuccess"
      variant="plain"
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
import { ref } from "vue";
import {
  onLoad,
  onShow,
  onHide,
  onShareAppMessage,
  onShareTimeline,
} from "@dcloudio/uni-app";
import {
  getXhsLevelPuzzle,
  loadXhsLevelList,
  pickXhsLevelFromProgress,
  prefetchNextXhsLevelImage,
} from "../../data/levels";
import { api } from "../../utils/api";
import { useNavBar } from "../../composables/useNavBar";
import PunPageNavBar from "../../components/PunPageNavBar.vue";
import PunPlayHintShareBar from "../../components/PunPlayHintShareBar.vue";
import PunPassSuccessOverlay from "../../components/PunPassSuccessOverlay.vue";
import PunHintOverlay from "../../components/PunHintOverlay.vue";
import PunWrongAnswerFloat from "../../components/PunWrongAnswerFloat.vue";
import { usePunPassSuccess } from "../composables/usePunPassSuccess";
import { usePunShareReward } from "../../composables/usePunShareReward";
import { usePunRewardedVideoHint } from "../composables/usePunRewardedVideoHint";
import { usePunHanAnswerInput } from "../composables/usePunHanAnswerInput";
import { usePunSkipLevel } from "../composables/usePunSkipLevel";
import { useWrongAnswerFloat } from "../composables/useWrongAnswerFloat";
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
const puzzle = ref({
  imageUrlTop: "",
  keywordHint: "",
  author: "",
});
const loading = ref(true);
const imageReady = ref(false);
const submitting = ref(false);
const hideKeywordHint = ref(false);

const feedback = ref([]);
const slotShake = ref(false);
const {
  wrongFloatItems,
  pushWrongFloatText,
  clearWrongFloatText,
} = useWrongAnswerFloat();
const { showSuccess, runPassSuccess, confirmPassSuccess } = usePunPassSuccess();
const passSuccessExplain = ref("");
const hintLoading = ref(false);
const skipLoading = ref(false);
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
const answerInputValue = ref("");
const {
  inputRef: xhsInputRef,
  caretIndex,
  onInputFocus,
  onInputBlur,
  focusInput,
  onAnswerInput,
} = usePunHanAnswerInput({
  answerLen,
  answerChars,
  answerInputValue,
  onFilled: () => checkAnswer(),
});

function isSlotError(index) {
  return punIsSlotError(feedback.value, index);
}

async function checkAnswer() {
  if (submitting.value) return;
  const userAnswer = answerChars.value.slice();
  if (userAnswer.length !== answerLen.value) return;
  submitting.value = true;
  feedback.value = [];

  try {
    const data = await api.submitAnswer(level.value, userAnswer, {
      gameTier: "xhs",
    });
    if (data.isCorrect) {
      clearWrongFloatText();
      const rawNextLevel = data && data.nextLevel != null ? Number(data.nextLevel) : null;
      const submitNextLevel = Number.isFinite(rawNextLevel) ? rawNextLevel : null;
      runPassSuccess({
        durationMs: 1200,
        manualClose: true,
        afterPrepare: async () => {
          applyPassExplain(data.passExplain);
          return submitNextLevel;
        },
        onAfter: (nextLevel) => {
          if (nextLevel == null) {
            uni.showToast({ title: "专辑持续更新中，敬请期待~", icon: "none" });
            setTimeout(() => {
              uni.reLaunch({ url: "/pages/index/index" });
            }, 1200);
            return;
          }
          uni.redirectTo({ url: `/pages-sub/playXhs/playXhs?level=${nextLevel}` });
        },
      });
      return;
    }
    feedback.value = data.feedback || [];
    pushWrongFloatText(userAnswer);
    punScheduleWrongAnswerReset(slotShake, () => {
      answerChars.value = [];
      answerInputValue.value = "";
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
  const hintPayload = {
    level: level.value,
    gameTier: "xhs",
  };
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

function help() {
  const p = (uni.getAppBaseInfo?.() ?? uni.getSystemInfoSync()).uniPlatform || "";
  if (!(p === "mp-weixin" || p === "mp-toutiao")) {
    uni.showToast({ title: "分享给好友一起猜～", icon: "none" });
  }
}

function back() {
  uni.reLaunch({ url: "/pages/index/index" });
}

function goFeedback() {
  const title = `小红书专辑 · 第${level.value}关`;
  const query = `type=bug&passedLevels=${encodeURIComponent(
    String(level.value)
  )}&content=${encodeURIComponent(title)}&contentFocus=1`;
  uni.navigateTo({ url: `/pages-sub/feedback/feedback?${query}` });
}

const { onSkipLevel } = usePunSkipLevel({
  mode: "xhs",
  levelRef: level,
  hintAnswerQuotaRef: hintAnswerQuota,
  skipLoadingRef: skipLoading,
  loadingRefs: [loading, submitting, hintLoading],
  isValidNextLevel: (n) => Number.isFinite(n) && n > 0,
  toNextUrl: (n) => `/pages-sub/playXhs/playXhs?level=${n}`,
});

onShow(() => {
  playBgmPlay();
});

onHide(() => {
  stopBgm();
});

onLoad(async (opts) => {
  let lv =
    opts && opts.level != null && opts.level !== "" ? parseInt(opts.level, 10) : NaN;
  const fromQuery = Number.isFinite(lv) && lv > 0;
  if (!fromQuery) {
    loading.value = true;
    try {
      const data = await api.getLevelProgress({ gameTier: "xhs" });
      if (typeof data.hintAnswerQuota === "number") {
        hintAnswerQuota.value = data.hintAnswerQuota;
      }
      await loadXhsLevelList();
      const resolved = pickXhsLevelFromProgress(data);
      lv = resolved != null ? resolved : 1;
    } catch {
      lv = 1;
    }
  } else {
    api
      .getLevelProgress({ gameTier: "xhs" })
      .then((data) => {
        if (typeof data.hintAnswerQuota === "number") {
          hintAnswerQuota.value = data.hintAnswerQuota;
        }
      })
      .catch(() => {});
  }

  level.value = lv;
  loading.value = true;
  imageReady.value = false;
  try {
    const raw = uni.getStorageSync('pun_hide_keyword_hint')
    hideKeywordHint.value = raw === true || raw === 'true' || raw === 1 || raw === '1'
  } catch {}
  getXhsLevelPuzzle(lv)
    .then((data) => {
      answerLen.value = data.answerLength || 3;
      puzzle.value = {
        imageUrlTop: data.imageUrlTop || data.imageUrl || "",
        keywordHint: data.keywordHint || "",
        author: data.author || "",
      };
      answerChars.value = [];
      feedback.value = [];
      answerInputValue.value = "";
      passSuccessExplain.value = "";
      loading.value = false;
      prefetchNextXhsLevelImage(lv);
    })
    .catch(() => {
      loading.value = false;
    });
});

onShareAppMessage(() => {
  return withShareReward({
    title: `小红书专辑·第${level.value}关，快来帮我猜！`,
    path: `/pages-sub/playXhs/playXhs?level=${level.value}`,
  });
});

onShareTimeline(() => {
  const img = puzzle.value.imageUrlTop || "";
  return {
    title: `小红书专辑·第${level.value}关，快来帮我猜！`,
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
  overflow: hidden;
  @include pt-page-background;
}

.card {
  position: relative;
  z-index: 2;
  margin-bottom: 20rpx;
  border-radius: 24rpx;
  overflow: visible;
  box-shadow: 0 12rpx 36rpx rgba(169, 201, 238, 0.22), 0 2rpx 10rpx rgba(0, 0, 0, 0.05);
  background: rgba(255, 255, 255, 0.95);
  border: 2rpx solid rgba(169, 201, 238, 0.55);
}

.keyword-tab {
  position: absolute;
  top: 26rpx;
  right: 18rpx;
  z-index: 4;
  min-height: auto;
  padding: 14rpx 20rpx;
  background: linear-gradient(145deg, #f6b0c3 0%, #ee8fad 100%);
  border-radius: 14rpx;
  box-shadow: 0 10rpx 24rpx rgba(238, 143, 173, 0.28);
}
.keyword-tab-text {
  font-size: 24rpx;
  font-weight: 700;
  color: #fff;
}

.author-tag {
  position: absolute;
  top: 26rpx;
  left: 18rpx;
  z-index: 4;
  padding: 12rpx 16rpx;
  background: rgba(255, 255, 255, 0.88);
  border: 1rpx solid rgba(169, 201, 238, 0.45);
  border-radius: 12rpx;
}
.author-text {
  font-size: 22rpx;
  color: #7d8fa0;
  font-weight: 600;
}

.card-inner {
  position: relative;
  min-height: 420rpx;
  padding: 20rpx;
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
  height: 846rpx;
  border-radius: 20rpx;
  overflow: hidden;
  background: #f0f4f8;
}
.main-img {
  width: 100%;
  height: 100%;
}
.img-placeholder {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  border-radius: 20rpx;
  background: rgba(240, 244, 248, 0.95);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 28rpx;
  color: #8a9aaa;
}

.answer-block {
  @include pt-answer-block;
}
.answer-row {
  @include pt-answer-row;
  border-bottom: 1rpx solid rgba(160, 190, 220, 0.35);
}
.answer-left {
  @include pt-answer-left;
}
.answer-input-cover {
  @include pt-answer-input-cover;
}
.answer-slots {
  @include pt-answer-slots;
}
.slot {
  @include pt-slot;
}
.slot-error {
  @include pt-slot-error;
}
.slot-shake {
  @include pt-slot-shake;
}
.slot-caret {
  @include pt-slot-caret;
}
@include pt-keyframes-caret-blink;
@include pt-keyframes-slot-shake;
</style>
