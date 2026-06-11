<template>
  <view class="page page--mid">
    <view class="bg-wrap">
      <view class="bg-gradient" />
      <view class="bg-dots" />
      <view class="bg-glow" />
    </view>

    <PunPageNavBar
      :status-bar-height="statusBarHeight"
      :nav-bar-height="navBarHeight"
      :menu-button-height="menuButtonHeight"
      @left-click="goBack"
    >
      <template #title>
        <text class="nav-title">1V1 对战 · 第 {{ currentQuestionIndex + 1 }}/5 题</text>
      </template>
    </PunPageNavBar>

    <!-- 对战进度条 -->
    <view class="battle-status">
      <view class="player-progress left">
        <text class="name">{{ myName }}</text>
        <view class="dots">
          <view
            v-for="i in 5"
            :key="i"
            class="dot"
            :class="{ active: myProgress >= i }"
          ></view>
        </view>
      </view>

      <view class="center-time">
        <text class="time-label">已用时</text>
        <text class="time-value">{{ formatTime(globalTimeMs) }}</text>
      </view>

      <view class="player-progress right">
        <text class="name">{{ opponentName }}</text>
        <view class="dots">
          <view
            v-for="i in 5"
            :key="i"
            class="dot oppo"
            :class="{ active: opponentProgress >= i }"
          ></view>
        </view>
      </view>
    </view>

    <view class="card card--mid">
      <view v-if="puzzle.keywordHint" class="keyword-tab">
        <text class="keyword-tab-text">提示：{{ puzzle.keywordHint }}</text>
      </view>

      <view v-if="puzzle.author" class="author-tag">
        <text class="author-text">作者：{{ puzzle.author }}</text>
      </view>
      <view class="card-inner card-inner--stack">
        <PunGlobalWatermark />
        <PunWrongAnswerFloat :items="wrongFloatItems" />
        <view class="stack-block">
          <image
            v-if="puzzle.imageUrlTop"
            class="stack-img"
            :class="{ 'stack-img--xhs': questionBank === 'xhs' }"
            :src="puzzle.imageUrlTop"
            mode="aspectFill"
          />
          <view v-else-if="loading" class="stack-placeholder">加载中...</view>
          <view v-else class="stack-placeholder">暂无配图</view>
          <text v-if="puzzle.topCaption" class="stack-caption">{{
            puzzle.topCaption
          }}</text>
        </view>

        <view v-if="questionBank === 'mid' && puzzle.imageUrlBottom" class="stack-block">
          <image class="stack-img" :src="puzzle.imageUrlBottom" mode="aspectFill" />
          <text v-if="puzzle.bottomCaption" class="stack-caption">{{
            puzzle.bottomCaption
          }}</text>
        </view>
      </view>
    </view>

    <view class="answer-block answer-block--mid">
      <view class="answer-row answer-row--mid">
        <view class="answer-left" v-if="!finished">
          <input
            ref="midInputRef"
            class="answer-mid-input-cover"
            type="text"
            :value="answerInputValue"
            @input="onMidAnswerInput"
            confirm-type="done"
            @focus="onMidInputFocus"
            @blur="onMidInputBlur"
            @click.stop="focusMidInput()"
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

        <view class="answer-left finished-msg" v-else>
          <text v-if="gameOver">对战已结束，正在结算中...</text>
          <text v-else>你已完成所有题目，等待对手中...</text>
        </view>
      </view>

      <PunPlayHintShareBar
        v-if="!finished && !gameOver"
        :show-share="false"
        :hint-loading="hintLoading"
        :hint-answer-quota="hintAnswerQuota"
        :hint-locked="loading"
        @hint="onRevealHint"
      />
    </view>

    <PunPassSuccessOverlay :show="showSuccess" variant="battle" />
    <PunHintOverlay
      :show="hintOverlayVisible"
      :hint-text="hintOverlayText"
      :step="hintOverlayStep"
      :max-steps="hintOverlayMaxSteps"
      :is-complete="hintOverlayComplete"
      @close="hintOverlayVisible = false"
    />

    <!-- 结算弹层 -->
    <view v-if="gameOver" class="result-overlay">
      <view class="result-card">
        <text class="res-title">{{ resultTitle }}</text>
        <view class="res-detail">
          <text>你答对: {{ resultData.myProgress || 0 }} / 5 题</text>
          <text>对手答对: {{ resultData.opponentProgress || 0 }} / 5 题</text>
          <text>本局总耗时: {{ formatTime(resultData.totalTimeMs || 0) }}</text>
        </view>
        <button class="btn-home" @click="confirmBackToRoom">确认返回房间</button>
      </view>
    </view>
  </view>
</template>

<script setup>
import { ref, computed, onUnmounted } from "vue";
import { onLoad, onShow, onHide } from "@dcloudio/uni-app";
import {
  getXhsLevelPuzzle,
  getMidLevelPuzzle,
  prefetchBattleXhsImages,
  prefetchBattleMidImages,
} from "../../data/levels";
import { api } from "../../utils/api";
import { wsApi } from "../utils/ws";
import { useNavBar } from "../../composables/useNavBar";
import PunPageNavBar from "../../components/PunPageNavBar.vue";
import PunGlobalWatermark from "../../components/PunGlobalWatermark.vue";
import PunPlayHintShareBar from "../../components/PunPlayHintShareBar.vue";
import PunPassSuccessOverlay from "../../components/PunPassSuccessOverlay.vue";
import PunHintOverlay from "../../components/PunHintOverlay.vue";
import PunWrongAnswerFloat from "../../components/PunWrongAnswerFloat.vue";
import { usePunPassSuccess } from "../composables/usePunPassSuccess";
import { usePunRewardedVideoHint } from "../composables/usePunRewardedVideoHint";
import { usePunHanAnswerInput } from "../composables/usePunHanAnswerInput";
import { useWrongAnswerFloat } from "../composables/useWrongAnswerFloat";
import { playBgmPlay, stopBgm } from "../../utils/gameAudio";
import {
  punGetCachedHint,
  punIsSlotError,
  punScheduleWrongAnswerReset,
  punRevealHintWithModal,
  punToastRevealHintAfterError,
} from "../utils/punPlayShared";
import { getUserInfo, getAuthToken, wechatLogin } from "../../utils/auth";
import { useWechatPageShare } from "../composables/useWechatPageShare";

const { statusBarHeight, navBarHeight, menuButtonHeight } = useNavBar();

const shareRewardQuotaRef = ref(0);
useWechatPageShare("1V1 对战中 · 谐音梗猜一猜", shareRewardQuotaRef);

const roomId = ref("");
const levels = ref([]);
const currentQuestionIndex = ref(0);
const myUserId = ref(Number(getUserInfo()?.id || getUserInfo()?.user_id || 0));
const myName = ref("我");
const opponentName = ref("对手");

// 游戏状态
const myProgress = ref(0);
const opponentProgress = ref(0);
const myTimeMs = ref(0);
const opponentTimeMs = ref(0);
const globalTimeMs = ref(0);
const finished = ref(false);
const gameOver = ref(false);
const resultData = ref({});
const resultTitle = computed(() => {
  const winnerId = Number(resultData.value.winnerId || 0);
  const myId = Number(myUserId.value || 0);
  const reason = resultData.value.reason || '';
  if (!winnerId || winnerId === 0) return "势均力敌，平局！";
  if (reason === 'opponent_disconnected') {
    return winnerId === myId ? "对手断线，你获胜了" : "你已断线，本局失败";
  }
  return winnerId === myId ? "太棒了，你赢了！" : "很遗憾，你输了";
});

// 计时器
let startTime = 0;
let timer = null;
let reconnecting = false;

// 前后台切换兜底：确保一定能拿到最新的 room/game_over 状态
const WAKE_SYNC_FALLBACK_MS = 5000;
let wakeSyncTimer = null;
let lastJoinSyncAt = 0;
const JOIN_SYNC_MIN_MS = 1500;

// 题目数据
const answerLen = ref(3);
const answerChars = ref([]);
const puzzle = ref({
  imageUrlTop: "",
  imageUrlBottom: "",
  topCaption: "",
  bottomCaption: "",
  keywordHint: "",
  author: "",
});
const loading = ref(true);
const submitting = ref(false);

const feedback = ref([]);
const slotShake = ref(false);
const {
  wrongFloatItems,
  pushWrongFloatText,
  clearWrongFloatText,
} = useWrongAnswerFloat();
const { showSuccess, runPassSuccess } = usePunPassSuccess();
const hintLoading = ref(false);
const hintAnswerQuota = ref(0);
const hintOverlayVisible = ref(false);
const hintOverlayText = ref("");
const hintOverlayStep = ref(0);
const hintOverlayMaxSteps = ref(0);
const hintOverlayComplete = ref(false);
const { tryWatchAdForHintQuota } = usePunRewardedVideoHint(hintAnswerQuota);

const answerInputValue = ref("");
const {
  inputRef: midInputRef,
  caretIndex,
  onInputFocus: onMidInputFocus,
  onInputBlur: onMidInputBlur,
  focusInput: focusMidInput,
  onAnswerInput: onMidAnswerInput,
} = usePunHanAnswerInput({
  answerLen,
  answerChars,
  answerInputValue,
  onFilled: () => checkAnswer(),
});

function formatTime(ms) {
  return (ms / 1000).toFixed(1) + "s";
}

async function onRevealHint() {
  if (finished.value || gameOver.value || hintLoading.value || loading.value) return;
  const lv = parseInt(levels.value[currentQuestionIndex.value], 10);
  if (!Number.isFinite(lv) || !roomId.value) return;
  const hintPayload = {
    level: lv,
    gameTier: "battle",
    roomId: roomId.value,
    questionIndex: currentQuestionIndex.value,
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

function refreshHintAnswerQuota() {
  api
    .getLevelProgress({ gameTier: "mid" })
    .then((data) => {
      if (typeof data.hintAnswerQuota === "number") {
        hintAnswerQuota.value = data.hintAnswerQuota;
      }
    })
    .catch(() => {});
}

function isSlotError(index) {
  return punIsSlotError(feedback.value, index);
}

async function checkAnswer() {
  if (submitting.value || finished.value || gameOver.value) return;
  const userAnswer = answerChars.value.slice();
  if (userAnswer.length !== answerLen.value) return;
  submitting.value = true;
  feedback.value = [];

  try {
    const currentLevelId = parseInt(levels.value[currentQuestionIndex.value], 10);
    // 使用 battle mode，不更新个人关卡进度
    const data = await api.submitAnswer(currentLevelId, userAnswer, {
      gameTier: "battle",
      questionBank: questionBank.value,
    });
    // 修改这行：因为 submitAnswer 返回的结果结构有可能是 { isCorrect: true } 或者是原格式，根据实际情况适配
    if (data && data.isCorrect) {
      // 记录正确答案
      clearWrongFloatText();
      runPassSuccess({
        durationMs: 1000,
        afterPrepare: async () => {
          // 更新进度
          myProgress.value++;
          const currentPassedTime = Date.now() - startTime;
          await ensureBattleWsConnected();
          wsApi.send({
            action: "progress",
            questionIndex: myProgress.value,
            timeMs: currentPassedTime,
          });

          // 最后一题答对后立刻结束，避免对手在动画期间继续作答
          if (myProgress.value >= 5) {
            finished.value = true;
            myTimeMs.value = currentPassedTime;
            if (timer) clearInterval(timer);
            await ensureBattleWsConnected();
            wsApi.send({
              action: "finish",
              totalTimeMs: currentPassedTime,
            });
          }
        },
        onAfter: () => {
          if (myProgress.value < 5) {
            currentQuestionIndex.value++;
            loadCurrentQuestion();
          }
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

function loadCurrentQuestion() {
  if (currentQuestionIndex.value >= 5) return;
  const lv = levels.value[currentQuestionIndex.value];
  loading.value = true;

  const puzzleLoader =
    questionBank.value === "mid" ? getMidLevelPuzzle : getXhsLevelPuzzle;
  puzzleLoader(lv)
    .then((data) => {
      answerLen.value = data.answerLength || 3;
      puzzle.value = {
        imageUrlTop: data.imageUrlTop || data.imageUrl || "",
        imageUrlBottom: data.imageUrlBottom || "",
        topCaption: data.topCaption || "",
        bottomCaption: data.bottomCaption || "",
        keywordHint: data.keywordHint || "",
        author: data.author || "",
      };
      answerChars.value = [];
      feedback.value = [];
      answerInputValue.value = "";
      loading.value = false;
    })
    .catch((err) => {
      console.error("加载题目失败", err);
      loading.value = false;
      uni.showToast({ title: "加载题目失败", icon: "none" });
    });
}

function goBack() {
  const pages = getCurrentPages();
  if (pages.length > 1) {
    uni.navigateBack();
  } else {
    uni.reLaunch({ url: "/pages/index/index" });
  }
}

onShow(() => {
  if (gameOver.value) return;
  refreshHintAnswerQuota();
  // 唤醒后先同步一次，随后如果仍未结算再兜底同步
  if (wakeSyncTimer) {
    clearTimeout(wakeSyncTimer);
    wakeSyncTimer = null;
  }
  syncBattleStateForWake();
  wakeSyncTimer = setTimeout(() => {
    if (!gameOver.value) syncBattleStateForWake();
  }, WAKE_SYNC_FALLBACK_MS);
  playBgmPlay();
});

onHide(() => {
  stopBgm();
});

onUnmounted(() => {
  if (timer) clearInterval(timer);
  wsApi.off("sync_progress");
  wsApi.off("opponent_finish");
  wsApi.off("game_over");
  wsApi.off("resume_game");
  wsApi.off("error");
  if (wakeSyncTimer) {
    clearTimeout(wakeSyncTimer);
    wakeSyncTimer = null;
  }
});

const questionBank = ref("xhs");

onLoad((opts) => {
  if (opts.roomId) roomId.value = opts.roomId;
  if (opts.myUserId) {
    myUserId.value = Number(opts.myUserId || 0);
  }
  if (opts.questionBank) questionBank.value = opts.questionBank;
  if (opts.levels) {
    try {
      levels.value = JSON.parse(opts.levels);
    } catch (e) {}
  }
  if (Array.isArray(levels.value) && levels.value.length > 0) {
    if (questionBank.value === "mid") {
      prefetchBattleMidImages(levels.value);
    } else {
      prefetchBattleXhsImages(levels.value);
    }
  }
  if (opts.myName) myName.value = decodeURIComponent(opts.myName);
  if (opts.opponentName) opponentName.value = decodeURIComponent(opts.opponentName);
  ensureBattleWsConnected();
  refreshHintAnswerQuota();

  // 监听 WS
  wsApi.on("sync_progress", (data) => {
    opponentProgress.value = data.questionIndex;
    opponentTimeMs.value = data.timeMs;
  });

  wsApi.on("opponent_finish", (data) => {
    opponentTimeMs.value = data.timeMs;
  });

  wsApi.on("game_over", (data) => {
    gameOver.value = true;
    finished.value = true;
    submitting.value = false;
    if (wakeSyncTimer) {
      clearTimeout(wakeSyncTimer);
      wakeSyncTimer = null;
    }
    if (timer) clearInterval(timer);
    // 结束态如后端下发了双方最终进度，则直接覆盖 dots，确保一致性
    if (data && data.myProgress !== undefined)
      myProgress.value = Number(data.myProgress || 0);
    if (data && data.opponentProgress !== undefined)
      opponentProgress.value = Number(data.opponentProgress || 0);
    resultData.value = {
      winnerId: data.winnerId,
      totalTimeMs: Number(data.totalTimeMs || 0),
      reason: data.reason || '',
      myProgress: Number(data.myProgress || 0),
      opponentProgress: Number(data.opponentProgress || 0),
    };
    const winnerId = Number(data.winnerId || 0);
    const myId = Number(myUserId.value || 0);
    const endMsg = !winnerId
      ? "本局平局，本局结束"
      : winnerId === myId
      ? "你已获胜，本局结束"
      : "对手已获胜，本局结束";
    uni.showToast({ title: endMsg, icon: "none" });
  });

  wsApi.on("error", (data) => {
    const msg = data && data.msg ? String(data.msg) : "";
    // 理论上已结束房间 join 会直接返回 game_over；这里仅做兼容兜底
    if (msg.includes("不存在") || msg.includes("解散")) {
      uni.showToast({ title: "对战已结束，请稍后重试或查看记录", icon: "none" });
      setTimeout(() => {
        uni.redirectTo({ url: "/pages-sub/battleHistory/battleHistory" });
      }, 800);
    }
  });

  wsApi.on("resume_game", (data) => {
    myProgress.value = Number(data.myProgress || 0);
    opponentProgress.value = Number(data.opponentProgress || 0);
    currentQuestionIndex.value = myProgress.value;
    const serverPassedTime = Number(data.timePassed || 0);
    startTime = Date.now() - serverPassedTime;
    if (data.myName) myName.value = data.myName;
    if (data.opponentName) opponentName.value = data.opponentName;

    if (myProgress.value >= 5) {
      finished.value = true;
    } else {
      finished.value = false;
      loadCurrentQuestion();
    }
  });

  // 开始计时和加载第一题
  startTime = Date.now();
  if (opts.resume === "1") {
    myProgress.value = parseInt(opts.myProgress || "0", 10);
    opponentProgress.value = parseInt(opts.opponentProgress || "0", 10);
    currentQuestionIndex.value = myProgress.value;

    let serverPassedTime = parseInt(opts.timePassed || "0", 10);
    startTime = Date.now() - serverPassedTime;
  }

  // 250ms 刷新已足够平滑显示「已用时」，避免 100ms 触发过多次 Vue 更新
  timer = setInterval(() => {
    if (!finished.value) {
      globalTimeMs.value = Date.now() - startTime;
      myTimeMs.value = globalTimeMs.value;
    }
  }, 250);

  if (myProgress.value >= 5) {
    finished.value = true;
  } else {
    loadCurrentQuestion();
  }
});

function confirmBackToRoom() {
  wsApi.close();
  uni.redirectTo({ url: "/pages-sub/battleRoom/battleRoom?fromBattle=1" });
}

async function ensureBattleWsConnected() {
  let token = getAuthToken();
  if (!token) {
    try {
      await wechatLogin();
      token = getAuthToken();
    } catch (e) {
      return;
    }
  }
  if (!token) return;
  const wasConnected = wsApi.isConnected && wsApi.isConnected();
  try {
    const authData = await wsApi.connect(token);
    if (authData && authData.userInfo && authData.userInfo.id) {
      myUserId.value = Number(authData.userInfo.id);
    }
    // 仅在断线重连后，重新 join 房间恢复服务端房间上下文
    if (!wasConnected && roomId.value) {
      wsApi.send({ action: "join", roomId: roomId.value });
    }
  } catch (e) {
    uni.showToast({ title: "对战连接异常，请重试", icon: "none" });
  }
}

async function reconnectBattleState() {
  if (reconnecting || !roomId.value) return;
  reconnecting = true;
  try {
    await ensureBattleWsConnected();
  } finally {
    reconnecting = false;
  }
}

async function syncBattleStateForWake() {
  if (!roomId.value) return;

  const now = Date.now();
  if (now - lastJoinSyncAt < JOIN_SYNC_MIN_MS) return;
  lastJoinSyncAt = now;

  await ensureBattleWsConnected();
  // 无条件 join：服务端会根据房间状态返回 resume_game 或 game_over
  wsApi.send({ action: "join", roomId: roomId.value });
}
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
  max-width: 100vh;
  overflow: hidden;
  @include pt-page-background;
}

.battle-status {
  position: relative;
  z-index: 2;
  display: flex;
  align-items: center;
  justify-content: space-between;
  background: rgba(255, 255, 255, 0.8);
  border-radius: 20rpx;
  padding: 20rpx;
  margin-bottom: 24rpx;
}
.player-progress {
  display: flex;
  flex-direction: column;
  gap: 10rpx;
}
.player-progress.left {
  align-items: flex-start;
}
.player-progress.right {
  align-items: flex-end;
}
.name {
  font-size: 24rpx;
  font-weight: bold;
  color: #475569;
}
.dots {
  display: flex;
  gap: 8rpx;
}
.dot {
  width: 24rpx;
  height: 24rpx;
  border-radius: 50%;
  background: #cbd5e1;
}
.dot.active {
  background: #3b82f6;
}
.dot.oppo.active {
  background: #ef4444;
}
.center-time {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
}
.time-label {
  font-size: 20rpx;
  color: #94a3b8;
  margin-bottom: 4rpx;
}
.time-value {
  font-size: 36rpx;
  font-weight: 900;
  color: #5a6d7a;
  font-variant-numeric: tabular-nums;
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
.card--mid {
  overflow: visible;
  border-radius: 28rpx;
  box-shadow: 0 12rpx 36rpx rgba(169, 201, 238, 0.22), 0 2rpx 10rpx rgba(0, 0, 0, 0.05);
  border-color: rgba(169, 201, 238, 0.55);
}

.keyword-tab {
  position: absolute;
  top: 26rpx;
  right: 18rpx;
  z-index: 4;
  padding: 14rpx 20rpx;
  background: linear-gradient(145deg, #a8e6a2 0%, #91d58b 100%);
  border-radius: 14rpx;
  box-shadow: 0 10rpx 24rpx rgba(111, 184, 104, 0.28);
}
.keyword-tab-text {
  font-size: 26rpx;
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

.card-inner--stack {
  position: relative;
  padding: 20rpx;
  gap: 28rpx;
}
.stack-block {
  width: 100%;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 16rpx;
}
.stack-img {
  width: 100%;
  border-radius: 20rpx;
  background: #f0f4f8;
}
.stack-img--xhs {
  height: 846rpx;
}
.stack-placeholder {
  width: 100%;
  height: 300rpx;
  border-radius: 20rpx;
  background: rgba(240, 244, 248, 0.95);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 28rpx;
  color: #8a9aaa;
}
.stack-caption {
  font-size: 30rpx;
  font-weight: 700;
  color: #2c3a4a;
  margin-top: -60rpx;
}

.answer-block {
  @include pt-answer-block;
}
.answer-row {
  @include pt-answer-row;
}
.answer-row--mid {
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

.answer-mid-input-cover {
  @include pt-answer-input-cover;
}
.answer-slots {
  @include pt-answer-slots;
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
.slot-char {
  line-height: 1;
}
.slot-caret {
  @include pt-slot-caret;
}
@include pt-keyframes-caret-blink;

.slot-error {
  @include pt-slot-error;
}
.slot-shake {
  @include pt-slot-shake;
}
@include pt-keyframes-slot-shake;

.result-overlay {
  position: fixed;
  inset: 0;
  z-index: 999;
  background: rgba(0, 0, 0, 0.6);
  display: flex;
  align-items: center;
  justify-content: center;
}

.result-card {
  background: #fff;
  border-radius: 32rpx;
  padding: 60rpx;
  text-align: center;
  width: 80%;
}
.res-title {
  font-size: 48rpx;
  font-weight: 900;
  color: #1e293b;
  display: block;
  margin-bottom: 40rpx;
}
.res-detail {
  display: flex;
  flex-direction: column;
  gap: 20rpx;
  font-size: 28rpx;
  color: #475569;
  margin-bottom: 60rpx;
}
.btn-home {
  background: linear-gradient(145deg, #a8e6a2 0%, #91d58b 100%);
  color: #fff;
  border-radius: 100rpx;
  height: 88rpx;
  line-height: 88rpx;
  box-shadow: 0 8rpx 24rpx rgba(111, 184, 104, 0.3);
}
</style>
