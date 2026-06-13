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
      @left-click="confirmBack"
    >
      <template #title>
        <text class="nav-title"
          >每日挑战 · 第 {{ currentIndex + 1 }}/{{ totalQuestions }} 题</text
        >
      </template>
    </PunPageNavBar>

    <!-- 状态栏：进度点 + 倒计时 -->
    <view class="dc-bar">
      <view class="dc-dots">
        <view
          v-for="i in totalQuestions"
          :key="i"
          class="dc-dot"
          :class="{ 'dc-dot--done': score >= i, 'dc-dot--wrong': wrongIndex === i - 1 }"
        />
      </view>
      <view class="dc-timer" :class="{ 'dc-timer--urgent': remainingSec <= 30 }">
        <text class="dc-timer-label">{{ remainingSec <= 30 ? "⚠ 剩余" : "剩余" }}</text>
        <text class="dc-timer-value">{{ formatCountdown(remainingMs) }}</text>
      </view>
    </view>

    <!-- 题目卡片 -->
    <view class="card">
      <view v-if="puzzle.keywordHint" class="keyword-tab">
        <text class="keyword-tab-text">提示：{{ puzzle.keywordHint }}</text>
      </view>
      <view v-if="puzzle.author" class="author-tag">
        <text class="author-text">作者：{{ puzzle.author }}</text>
      </view>
      <view class="card-inner">
        <PunGlobalWatermark />
        <PunWrongAnswerFloat :items="wrongFloatItems" />
        <view class="img-box">
          <image
            v-if="puzzle.imageUrlTop"
            class="card-img"
            :src="puzzle.imageUrlTop"
            mode="aspectFill"
            @load="imageReady = true"
            @error="imageReady = true"
          />
          <view v-if="!imageReady" class="card-placeholder">
            <text v-if="puzzle.imageUrlTop || loading">加载中...</text>
            <text v-else>暂无配图</text>
          </view>
        </view>
      </view>
    </view>

    <!-- 答案输入区 -->
    <view class="answer-block">
      <view class="answer-row">
        <view class="answer-left" v-if="!finished">
          <input
            ref="answerInputRef"
            class="answer-input-cover"
            type="text"
            :value="answerInputValue"
            @input="onAnswerInput"
            confirm-type="done"
            @focus="onInputFocus"
            @blur="onInputBlur"
          />
          <view class="answer-slots" @click.stop="focusInput()">
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
        <view class="answer-left finished-msg" v-else>
          <text v-if="passed">🎉 挑战成功！</text>
          <text v-else>挑战结束</text>
        </view>
      </view>

      <PunPlayHintShareBar
        :hint-loading="hintLoading"
        :hint-answer-quota="hintAnswerQuota"
        :hint-locked="loading"
        @hint="onRevealHint"
      />
    </view>

    <!-- 答对庆祝 -->
    <PunPassSuccessOverlay
      :show="passSuccessVisible"
      variant="plain"
      :sub-text="passSuccessExplain"
      :tap-anywhere="true"
      @action="onPassSuccessComplete"
    />

    <!-- 提示弹窗 -->
    <PunHintOverlay
      :show="hintOverlayVisible"
      :hint-text="hintOverlayText"
      :step="hintOverlayStep"
      :max-steps="hintOverlayMaxSteps"
      :is-complete="hintOverlayComplete"
      @close="hintOverlayVisible = false"
    />

    <!-- 结算弹层 -->
    <view v-if="resultVisible" class="result-overlay" @click.stop>
      <view class="result-card">
        <text class="res-icon">{{ passed ? "🎉" : "😢" }}</text>
        <text class="res-title">{{ passed ? "挑战成功" : "挑战失败" }}</text>

        <view class="res-score">
          <text class="res-score-num">{{ score }}</text>
          <text class="res-score-total">/ {{ totalQuestions }}</text>
        </view>
        <text class="res-label">答对题数</text>

        <view class="res-time">
          <text class="res-time-val">{{ formatTime(resultTimeMs) }}</text>
        </view>
        <text class="res-label">总耗时</text>

        <view v-if="passed" class="res-reward">
          <text class="res-reward-icon">🎁</text>
          <text>+{{ hintReward }} 答案次数</text>
        </view>
        <view v-else class="res-fail">
          <text>2 分钟内 10 题全对即可通关，请再次尝试</text>
        </view>

        <button class="btn-home" @click="goHome">返回首页</button>
      </view>
    </view>
  </view>
</template>

<script setup>
import { ref, computed, onUnmounted } from "vue";
import { onLoad } from "@dcloudio/uni-app";
import { getXhsLevelPuzzle } from "../../data/levels";
import { api } from "../../utils/api";
import { useNavBar } from "../../composables/useNavBar";
import PunPageNavBar from "../../components/PunPageNavBar.vue";
import PunGlobalWatermark from "../../components/PunGlobalWatermark.vue";
import PunPassSuccessOverlay from "../../components/PunPassSuccessOverlay.vue";
import PunHintOverlay from "../../components/PunHintOverlay.vue";
import PunPlayHintShareBar from "../../components/PunPlayHintShareBar.vue";
import PunWrongAnswerFloat from "../../components/PunWrongAnswerFloat.vue";
import { usePunHanAnswerInput } from "../composables/usePunHanAnswerInput";
import { useWrongAnswerFloat } from "../composables/useWrongAnswerFloat";
import {
  punIsSlotError,
  punScheduleWrongAnswerReset,
  punGetCachedHint,
  punRevealHintWithModal,
  punToastRevealHintAfterError,
} from "../utils/punPlayShared";

const TOTAL_TIME_MS = 120000;
const { statusBarHeight, navBarHeight, menuButtonHeight } = useNavBar();

const totalQuestions = 10;
const levels = ref([]);
const currentIndex = ref(0);
const score = ref(0);
const wrongIndex = ref(-1);
const slotShake = ref(false);
const loading = ref(true);
const imageReady = ref(false);
const finished = ref(false);
const submitting = ref(false);
const hintLoading = ref(false);
const hintAnswerQuota = ref(0);

const puzzle = ref({});

const passSuccessVisible = ref(false);
const passSuccessExplain = ref("");
const resultVisible = ref(false);
const passed = ref(false);
const resultTimeMs = ref(0);
const hintReward = ref(0);

const hintOverlayVisible = ref(false);
const hintOverlayText = ref("");
const hintOverlayStep = ref(0);
const hintOverlayMaxSteps = ref(0);
const hintOverlayComplete = ref(false);

const startTime = ref(0);
const remainingMs = ref(TOTAL_TIME_MS);
let timer = null;
let pageDestroyed = false;

const answerLen = ref(3);
const answerChars = ref([]);
const answerInputValue = ref("");
const feedback = ref([]);

const {
  inputRef: answerInputRef,
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

const {
  wrongFloatItems,
  pushWrongFloatText,
  clearWrongFloatText,
} = useWrongAnswerFloat();

function formatCountdown(ms) {
  const totalSec = Math.max(0, ms / 1000);
  const m = Math.floor(totalSec / 60);
  const s = Math.floor(totalSec % 60);
  const ds = Math.floor((totalSec % 1) * 10);
  return m + ":" + String(s).padStart(2, "0") + "." + ds;
}
function formatTime(ms) {
  return (ms / 1000).toFixed(1) + "s";
}

function refreshHintQuota() {
  api
    .getHintQuota()
    .then((data) => {
      if (typeof data.hintAnswerQuota === "number")
        hintAnswerQuota.value = data.hintAnswerQuota;
    })
    .catch(() => {});
}

const remainingSec = computed(() => Math.max(0, Math.ceil(remainingMs.value / 1000)));

function isSlotError(idx) {
  return punIsSlotError(feedback.value, idx);
}

function startTimer() {
  startTime.value = Date.now();
  timer = setInterval(() => {
    if (pageDestroyed) return;
    const elapsed = Date.now() - startTime.value;
    remainingMs.value = Math.max(0, TOTAL_TIME_MS - elapsed);
    if (remainingMs.value <= 0) {
      clearInterval(timer);
      timer = null;
      onTimeout();
    }
  }, 100);
}

function stopTimer() {
  if (timer) {
    clearInterval(timer);
    timer = null;
  }
}

async function loadPuzzle(levelNo) {
  loading.value = true;
  imageReady.value = false;
  answerChars.value = [];
  answerInputValue.value = "";
  feedback.value = [];
  try {
    const data = await getXhsLevelPuzzle(levelNo);
    if (!data) throw new Error("题目加载失败");
    puzzle.value = data;
    answerLen.value = data.answerLength || 1;
  } catch (e) {
    uni.showToast({ title: "题目加载失败", icon: "none" });
  } finally {
    loading.value = false;
  }
}

function onTimeout() {
  if (finished.value || resultVisible.value) return;
  finished.value = true;
  submitResult();
}

async function checkAnswer() {
  if (submitting.value || finished.value || resultVisible.value) return;
  const userAnswer = answerChars.value.slice();
  if (userAnswer.length !== answerLen.value) return;
  submitting.value = true;
  feedback.value = [];

  try {
    const lv = parseInt(levels.value[currentIndex.value], 10);
    const data = await api.submitAnswer(lv, userAnswer, { gameTier: "daily_challenge" });

    if (data && data.isCorrect) {
      clearWrongFloatText();
      score.value++;
      passSuccessExplain.value = data.passExplain || "";
      passSuccessVisible.value = true;
    } else {
      wrongIndex.value = currentIndex.value;
      const wrongTexts = [];
      if (Array.isArray(data.feedback)) {
        data.feedback.forEach((fb) => {
          if (fb && !fb.isCorrect) wrongTexts.push(fb.position);
        });
      }
      feedback.value = Array.isArray(data.feedback) ? data.feedback : [];
      if (wrongTexts.length > 0) pushWrongFloatText(wrongTexts);
      punScheduleWrongAnswerReset(slotShake, () => {
        answerChars.value = [];
        answerInputValue.value = "";
        feedback.value = [];
      });
      finished.value = true;
      stopTimer();
      setTimeout(() => submitResult(), 800);
    }
  } catch (e) {
    uni.showToast({ title: "提交失败", icon: "none" });
  } finally {
    submitting.value = false;
  }
}

async function onPassSuccessComplete() {
  passSuccessVisible.value = false;
  if (finished.value) return;

  if (score.value >= totalQuestions) {
    finished.value = true;
    stopTimer();
    submitResult();
  } else {
    currentIndex.value++;
    passSuccessExplain.value = "";
    answerChars.value = [];
    answerInputValue.value = "";
    feedback.value = [];
    await loadPuzzle(levels.value[currentIndex.value]);
  }
}

async function submitResult() {
  if (resultVisible.value) return;
  const elapsed = Date.now() - startTime.value;
  resultTimeMs.value = elapsed;
  passed.value = score.value >= totalQuestions && elapsed <= TOTAL_TIME_MS;

  try {
    const res = await api.finishDailyChallenge({
      score: score.value,
      totalTimeMs: elapsed,
    });
    if (res && res.passed) hintReward.value = res.hintReward || 5;
  } catch (e) {
    /* ignore */
  }

  resultVisible.value = true;
}

async function onRevealHint() {
  if (finished.value || resultVisible.value || hintLoading.value || loading.value) return;
  const lv = parseInt(levels.value[currentIndex.value], 10);
  if (!Number.isFinite(lv)) return;

  const hintPayload = { level: lv, gameTier: "daily_challenge" };
  const cached = punGetCachedHint(hintPayload);
  if (cached && cached.isComplete) {
    hintOverlayText.value = String(cached.hintText || "");
    hintOverlayStep.value = Number(cached.step || 0);
    hintOverlayMaxSteps.value = Number(cached.maxSteps || 0);
    hintOverlayComplete.value = true;
    hintOverlayVisible.value = true;
    return;
  }

  hintLoading.value = true;
  try {
    const res = await punRevealHintWithModal(hintPayload);
    if (typeof res.hintAnswerQuota === "number")
      hintAnswerQuota.value = res.hintAnswerQuota;
    hintOverlayText.value = String(res.hintText || "");
    hintOverlayStep.value = Number(res.step || 0);
    hintOverlayMaxSteps.value = Number(res.maxSteps || 0);
    hintOverlayComplete.value = !!res.isComplete;
    hintOverlayVisible.value = true;
  } catch (e) {
    punToastRevealHintAfterError(e, false);
  } finally {
    hintLoading.value = false;
  }
}

function confirmBack() {
  if (finished.value || resultVisible.value) {
    goHome();
    return;
  }
  uni.showModal({
    title: "退出挑战",
    content: "确定退出吗？本局将视为失败。",
    confirmText: "确定退出",
    cancelText: "继续挑战",
    success: (res) => {
      if (res.confirm) {
        finished.value = true;
        stopTimer();
        submitResult();
      }
    },
  });
}

function goHome() {
  uni.reLaunch({ url: "/pages/index/index" });
}

onUnmounted(() => {
  pageDestroyed = true;
  stopTimer();
});

onLoad(async () => {
  try {
    const data = await api.startDailyChallenge();
    if (data && data.closed) {
      uni.showToast({
        title: data.message || "每日挑战暂未开放",
        icon: "none",
        duration: 2500,
      });
      setTimeout(() => goHome(), 2500);
      return;
    }
    if (data && data.alreadyPassed) {
      uni.showToast({ title: "今日已通关，明天再来", icon: "none", duration: 2000 });
      setTimeout(() => goHome(), 2000);
      return;
    }
    if (!data || !Array.isArray(data.levels) || data.levels.length === 0) {
      uni.showToast({ title: "今日挑战暂不可用", icon: "none" });
      setTimeout(() => goHome(), 1500);
      return;
    }
    levels.value = data.levels;
    refreshHintQuota();
    await loadPuzzle(levels.value[0]);
    startTimer();
  } catch (e) {
    uni.showToast({ title: "加载失败", icon: "none" });
    setTimeout(() => goHome(), 1500);
  }
});
</script>

<style lang="scss" scoped>
@use "../../styles/page-theme.scss" as *;

// 穿透 PunPageNavBar 的 scoped 边界，确保标题左对齐
:deep(.nav-center) {
  justify-content: flex-start;
}
:deep(.nav-title) {
  text-align: left;
}

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

/* ===== 顶栏：进度点 + 倒计时 ===== */
.dc-bar {
  position: relative;
  z-index: 2;
  width: 100%;
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-top: 20rpx;
  margin-bottom: 20rpx;
  padding: 16rpx 24rpx;
  background: rgba(255, 255, 255, 0.72);
  border-radius: 20rpx;
  border: 2rpx solid rgba(169, 201, 238, 0.35);
  box-sizing: border-box;
}

.dc-dots {
  display: flex;
  gap: 8rpx;
}
.dc-dot {
  width: 18rpx;
  height: 18rpx;
  border-radius: 50%;
  background: rgba(0, 0, 0, 0.08);
  border: 2rpx solid rgba(0, 0, 0, 0.06);
  transition: all 0.3s;
}
.dc-dot--done {
  background: #10b981;
  border-color: #059669;
  box-shadow: 0 0 8rpx rgba(16, 185, 129, 0.35);
}
.dc-dot--wrong {
  background: #ef4444;
  border-color: #dc2626;
}

.dc-timer {
  display: flex;
  align-items: baseline;
  gap: 6rpx;
}
.dc-timer-label {
  font-size: 22rpx;
  color: #8aa0bd;
  font-weight: 600;
}
.dc-timer-value {
  font-size: 32rpx;
  font-weight: 900;
  color: #3a86da;
  font-variant-numeric: tabular-nums;
}
.dc-timer--urgent .dc-timer-value {
  color: #e53e3e;
  animation: timer-urgent-pulse 0.6s ease-in-out infinite;
}

@keyframes timer-urgent-pulse {
  0%,
  100% {
    transform: scale(1);
  }
  50% {
    transform: scale(1.06);
  }
}

/* ===== 题目卡片 ===== */
.card {
  position: relative;
  z-index: 2;
  width: 100%;
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
  color: #b08040;
}
.card-inner {
  position: relative;
  min-height: 420rpx;
  padding: 20rpx;
}
.img-box {
  position: relative;
  width: 100%;
  height: 846rpx;
  border-radius: 20rpx;
  overflow: hidden;
  background: #f0f4f8;
}
.card-img {
  width: 100%;
  height: 100%;
}
.card-placeholder {
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

/* ===== 答案输入 ===== */
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
.finished-msg {
  font-size: 30rpx;
  font-weight: bold;
  color: #10b981;
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
.slot-char {
  line-height: 1;
}
.slot-caret {
  @include pt-slot-caret;
}
.slot-error {
  @include pt-slot-error;
}
.slot-shake {
  @include pt-slot-shake;
}
@include pt-keyframes-slot-shake;

/* ===== 结算弹层 ===== */
.result-overlay {
  position: fixed;
  inset: 0;
  z-index: 200;
  background: rgba(0, 0, 0, 0.45);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 48rpx;
  box-sizing: border-box;
}
.result-card {
  width: 100%;
  max-width: 520rpx;
  background: #fff;
  border-radius: 36rpx;
  padding: 56rpx 40rpx 40rpx;
  display: flex;
  flex-direction: column;
  align-items: center;
  box-shadow: 0 24rpx 60rpx rgba(0, 0, 0, 0.15);
}
.res-icon {
  font-size: 88rpx;
  margin-bottom: 8rpx;
}
.res-title {
  font-size: 38rpx;
  font-weight: 900;
  color: #1e293b;
  margin-bottom: 32rpx;
}

.res-score {
  display: flex;
  align-items: baseline;
  gap: 4rpx;
  margin-bottom: 4rpx;
}
.res-score-num {
  font-size: 64rpx;
  font-weight: 900;
  color: #3b82f6;
  line-height: 1;
  font-variant-numeric: tabular-nums;
}
.res-score-total {
  font-size: 28rpx;
  color: #94a3b8;
  font-weight: 600;
}
.res-label {
  font-size: 22rpx;
  color: #94a3b8;
  margin-bottom: 20rpx;
}

.res-time {
  margin-bottom: 4rpx;
}
.res-time-val {
  font-size: 36rpx;
  font-weight: 800;
  color: #475569;
}
.res-reward {
  display: flex;
  align-items: center;
  gap: 8rpx;
  margin: 8rpx 0 28rpx;
  padding: 16rpx 32rpx;
  background: linear-gradient(135deg, #ecfdf5, #d1fae5);
  border-radius: 16rpx;
  font-size: 28rpx;
  font-weight: 800;
  color: #059669;
}
.res-reward-icon {
  font-size: 32rpx;
}
.res-fail {
  margin: 8rpx 0 28rpx;
  padding: 16rpx 28rpx;
  font-size: 24rpx;
  color: #94a3b8;
  text-align: center;
  line-height: 1.5;
}
.btn-home {
  width: 100%;
  height: 88rpx;
  line-height: 88rpx;
  border-radius: 24rpx;
  border: none;
  background: linear-gradient(180deg, #a3dd9c 0%, #91d58b 100%);
  color: #fff;
  font-size: 30rpx;
  font-weight: 700;
}
.btn-home::after {
  border: none;
}
</style>
