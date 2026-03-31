<template>
  <view class="page page--mid">
    <view class="bg-wrap">
      <view class="bg-gradient" />
      <view class="bg-dots" />
    </view>

    <!-- 顶部状态栏占位 -->
    <view :style="{ height: statusBarHeight + 'px' }"></view>

    <view class="nav-bar" :style="{ height: navBarHeight + 'px' }">
      <view class="nav-btn" @click="goBack" :style="{ width: menuButtonHeight + 'px', height: menuButtonHeight + 'px' }">
        <text class="nav-icon">🏠</text>
      </view>
      <view class="nav-center">
        <text class="nav-title">1V1 对战 · 第 {{ currentQuestionIndex + 1 }}/5 题</text>
      </view>
    </view>
    
    <!-- 对战进度条 -->
    <view class="battle-status">
      <view class="player-progress left">
        <text class="name">{{ myName }}</text>
        <view class="dots">
          <view v-for="i in 5" :key="i" class="dot" :class="{ 'active': myProgress >= i }"></view>
        </view>
      </view>
      
      <view class="center-time">
        <text class="time-label">已用时</text>
        <text class="time-value">{{ formatTime(globalTimeMs) }}</text>
      </view>

      <view class="player-progress right">
        <text class="name">{{ opponentName }}</text>
        <view class="dots">
          <view v-for="i in 5" :key="i" class="dot oppo" :class="{ 'active': opponentProgress >= i }"></view>
        </view>
      </view>
    </view>

    <view class="card card--mid">
      <view v-if="puzzle.keywordHint" class="keyword-tab">
        <text class="keyword-tab-text">{{ puzzle.keywordHint }}</text>
      </view>

      <view class="card-inner card-inner--stack">
        <view class="stack-block">
          <image v-if="puzzle.imageUrlTop" class="stack-img" :src="puzzle.imageUrlTop" mode="aspectFill" />
          <view v-else-if="loading" class="stack-placeholder">加载中...</view>
          <view v-else class="stack-placeholder">暂无配图</view>
          <text v-if="puzzle.topCaption" class="stack-caption">{{ puzzle.topCaption }}</text>
        </view>

        <view class="stack-block">
          <image v-if="puzzle.imageUrlBottom" class="stack-img" :src="puzzle.imageUrlBottom" mode="aspectFill" />
          <view v-else-if="loading" class="stack-placeholder">加载中...</view>
          <view v-else class="stack-placeholder">暂无下图</view>
          <text v-if="puzzle.bottomCaption" class="stack-caption">{{ puzzle.bottomCaption }}</text>
        </view>
      </view>
    </view>

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
            :class="['slot', { 'slot-error': isSlotError(i - 1), 'slot-shake': slotShake }]"
          >
            <text v-if="answerChars[i - 1]" class="slot-char">{{ answerChars[i - 1] }}</text>
            <view v-else-if="caretIndex === i - 1" class="slot-caret" />
          </view>
        </view>
      </view>
      
      <view class="answer-left finished-msg" v-else>
        <text>你已完成所有题目，等待对手中...</text>
      </view>
    </view>

    <!-- 通关成功动画弹层 -->
    <view v-if="showSuccess" class="success-overlay">
      <view class="success-content">
        <view class="success-icon-wrap">
          <text class="success-icon">🎉</text>
        </view>
        <text class="success-title">回答正确~</text>
      </view>
    </view>
    
    <!-- 结算弹层 -->
    <view v-if="gameOver" class="result-overlay">
      <view class="result-card">
        <text class="res-title">{{ resultTitle }}</text>
        <view class="res-detail">
          <text>本局总耗时: {{ formatTime(resultData.totalTimeMs || 0) }}</text>
        </view>
        <button class="btn-home" @click="confirmBackToRoom">确认返回房间</button>
      </view>
    </view>
  </view>
</template>

<script setup>
import { ref, computed, nextTick, onUnmounted } from 'vue'
import { onLoad, onShow, onHide } from '@dcloudio/uni-app'
import { getMidLevelPuzzle } from '../../data/levels'
import { api } from '../../utils/api'
import { wsApi } from '../../utils/ws'
import { useNavBar } from '../../composables/useNavBar'
import { playBgmPlay, stopBgm, playCongratsOnce } from '../../utils/gameAudio'
import { getUserInfo } from '../../utils/auth'

const { statusBarHeight, navBarHeight, menuButtonHeight } = useNavBar()

const roomId = ref('')
const levels = ref([])
const currentQuestionIndex = ref(0)
const myUserId = ref(Number(getUserInfo()?.id || getUserInfo()?.user_id || 0))
const myName = ref('我')
const opponentName = ref('对手')

// 游戏状态
const myProgress = ref(0)
const opponentProgress = ref(0)
const myTimeMs = ref(0)
const opponentTimeMs = ref(0)
const globalTimeMs = ref(0)
const finished = ref(false)
const gameOver = ref(false)
const resultData = ref({})
const resultTitle = computed(() => {
  const winnerId = Number(resultData.value.winnerId || 0)
  const myId = Number(myUserId.value || 0)
  if (!winnerId) return '势均力敌，平局！'
  return winnerId === myId ? '太棒了，你赢了！' : '很遗憾，你输了'
})

// 计时器
let startTime = 0
let timer = null
let reconnecting = false

// 题目数据
const answerLen = ref(3)
const answerChars = ref([])
const puzzle = ref({
  imageUrlTop: '',
  imageUrlBottom: '',
  topCaption: '',
  bottomCaption: '',
  keywordHint: '',
})
const loading = ref(true)
const submitting = ref(false)
const feedback = ref([])
const slotShake = ref(false)
const showSuccess = ref(false)

const answerInputValue = ref('')
const midInputRef = ref(null)
const midInputFocused = ref(false)

const caretIndex = computed(() => {
  if (!midInputFocused.value) return -1
  const n = answerChars.value.length
  if (n >= answerLen.value) return -1
  return n
})

function formatTime(ms) {
  return (ms / 1000).toFixed(1) + 's'
}

function isSlotError(index) {
  const fb = feedback.value[index]
  return fb && fb.isCorrect === false
}

function onMidInputFocus() {
  midInputFocused.value = true
}

function onMidInputBlur() {
  midInputFocused.value = false
}

function focusMidInput() {
  nextTick(() => {
    const el = midInputRef.value
    if (el && typeof el.focus === 'function') el.focus()
  })
}

function onMidAnswerInput(e) {
  const raw = (e.detail && e.detail.value) || ''
  answerInputValue.value = raw

  const hasHan = /[\u4e00-\u9fff]/.test(raw)
  if (!hasHan) {
    answerChars.value = []
    return
  }

  const parts = Array.from(raw).slice(0, answerLen.value)
  answerChars.value = parts
  if (parts.length === answerLen.value) checkAnswer()
}

async function checkAnswer() {
  if (submitting.value || finished.value || gameOver.value) return
  const userAnswer = answerChars.value.slice()
  if (userAnswer.length !== answerLen.value) return
  submitting.value = true
  feedback.value = []

  try {
    const currentLevelId = parseInt(levels.value[currentQuestionIndex.value], 10)
    // 使用 battle mode，不更新个人关卡进度
    const data = await api.submitAnswer(currentLevelId, userAnswer, { gameTier: 'battle' })
    // 修改这行：因为 submitAnswer 返回的结果结构有可能是 { isCorrect: true } 或者是原格式，根据实际情况适配
    if (data && data.isCorrect) {
      showSuccess.value = true
      playCongratsOnce()
      
      // 更新进度
      myProgress.value++
      const currentPassedTime = Date.now() - startTime
      await ensureBattleWsConnected()
      wsApi.send({
        action: 'progress',
        questionIndex: myProgress.value,
        timeMs: currentPassedTime
      })

      // 最后一题答对后立刻结束，避免对手在动画期间继续作答
      if (myProgress.value >= 5) {
        finished.value = true
        myTimeMs.value = currentPassedTime
        if (timer) clearInterval(timer)
        await ensureBattleWsConnected()
        wsApi.send({
          action: 'finish',
          totalTimeMs: currentPassedTime
        })
      }

      setTimeout(() => {
        showSuccess.value = false
        if (myProgress.value < 5) {
          // 下一题
          currentQuestionIndex.value++
          loadCurrentQuestion()
        }
      }, 1000)
      return
    }

    feedback.value = data.feedback || []
    slotShake.value = true
    uni.showToast({ title: '再想想～', icon: 'none' })
    setTimeout(() => {
      slotShake.value = false
      setTimeout(() => {
        answerChars.value = []
        answerInputValue.value = ''
        feedback.value = []
      }, 200)
    }, 600)
  } catch (e) {
    uni.showToast({ title: e.message || '提交失败', icon: 'none' })
  } finally {
    submitting.value = false
  }
}

function loadCurrentQuestion() {
  if (currentQuestionIndex.value >= 5) return
  const lv = levels.value[currentQuestionIndex.value]
  loading.value = true
  
  getMidLevelPuzzle(lv).then((data) => {
    answerLen.value = data.answerLength || 3
    puzzle.value = {
      imageUrlTop: data.imageUrlTop || '',
      imageUrlBottom: data.imageUrlBottom || '',
      topCaption: data.topCaption || '',
      bottomCaption: data.bottomCaption || '',
      keywordHint: data.keywordHint || '',
    }
    answerChars.value = []
    feedback.value = []
    answerInputValue.value = ''
    loading.value = false
  }).catch((err) => {
    console.error('加载题目失败', err)
    loading.value = false
    uni.showToast({ title: '加载题目失败', icon: 'none' })
  })
}


function goBack() {
  const pages = getCurrentPages()
  if (pages.length > 1) {
    uni.navigateBack()
  } else {
    uni.reLaunch({ url: '/pages/index/index' })
  }
}

onShow(() => {
  playBgmPlay()
  if (!gameOver.value) {
    reconnectBattleState()
  }
})

onHide(() => {
  stopBgm()
})

onUnmounted(() => {
  if (timer) clearInterval(timer)
  wsApi.off('sync_progress')
  wsApi.off('opponent_finish')
  wsApi.off('game_over')
  wsApi.off('resume_game')
})

onLoad((opts) => {
  if (opts.roomId) roomId.value = opts.roomId
  if (opts.myUserId) {
    myUserId.value = Number(opts.myUserId || 0)
  }
  if (opts.levels) {
    try {
      levels.value = JSON.parse(opts.levels)
    } catch (e) {}
  }
  if (opts.myName) myName.value = decodeURIComponent(opts.myName)
  if (opts.opponentName) opponentName.value = decodeURIComponent(opts.opponentName)
  ensureBattleWsConnected()

  // 监听 WS
  wsApi.on('sync_progress', (data) => {
    opponentProgress.value = data.questionIndex
    opponentTimeMs.value = data.timeMs
  })

  wsApi.on('opponent_finish', (data) => {
    opponentProgress.value = 5
    opponentTimeMs.value = data.timeMs
  })

  wsApi.on('game_over', (data) => {
    gameOver.value = true
    finished.value = true
    submitting.value = false
    if (timer) clearInterval(timer)
    opponentProgress.value = 5
    resultData.value = {
      winnerId: data.winnerId,
      totalTimeMs: Number(data.totalTimeMs || 0),
    }
    const winnerId = Number(data.winnerId || 0)
    const myId = Number(myUserId.value || 0)
    const endMsg = winnerId && winnerId === myId ? '你已获胜，本局结束' : '对手已获胜，本局结束'
    uni.showToast({ title: endMsg, icon: 'none' })
  })

  wsApi.on('resume_game', (data) => {
    myProgress.value = Number(data.myProgress || 0)
    opponentProgress.value = Number(data.opponentProgress || 0)
    currentQuestionIndex.value = myProgress.value
    const serverPassedTime = Number(data.timePassed || 0)
    startTime = Date.now() - serverPassedTime
    if (data.myName) myName.value = data.myName
    if (data.opponentName) opponentName.value = data.opponentName

    if (myProgress.value >= 5) {
      finished.value = true
    } else {
      finished.value = false
      loadCurrentQuestion()
    }
  })

  // 开始计时和加载第一题
  startTime = Date.now()
  if (opts.resume === '1') {
    myProgress.value = parseInt(opts.myProgress || '0', 10)
    opponentProgress.value = parseInt(opts.opponentProgress || '0', 10)
    currentQuestionIndex.value = myProgress.value
    
    let serverPassedTime = parseInt(opts.timePassed || '0', 10)
    startTime = Date.now() - serverPassedTime
  }

  timer = setInterval(() => {
    if (!finished.value) {
      globalTimeMs.value = Date.now() - startTime
      myTimeMs.value = globalTimeMs.value
    }
  }, 100)

  if (myProgress.value >= 5) {
    finished.value = true
  } else {
    loadCurrentQuestion()
  }
})

function confirmBackToRoom() {
  wsApi.close()
  uni.redirectTo({ url: '/pages/battleRoom/battleRoom' })
}

async function ensureBattleWsConnected() {
  const token = uni.getStorageSync('token')
  if (!token) return
  const wasConnected = wsApi.isConnected && wsApi.isConnected()
  try {
    const authData = await wsApi.connect(token)
    if (authData && authData.userInfo && authData.userInfo.id) {
      myUserId.value = Number(authData.userInfo.id)
    }
    // 仅在断线重连后，重新 join 房间恢复服务端房间上下文
    if (!wasConnected && roomId.value) {
      wsApi.send({ action: 'join', roomId: roomId.value })
    }
  } catch (e) {
    uni.showToast({ title: '对战连接异常，请重试', icon: 'none' })
  }
}

async function reconnectBattleState() {
  if (reconnecting || !roomId.value) return
  reconnecting = true
  try {
    await ensureBattleWsConnected()
  } finally {
    reconnecting = false
  }
}
</script>

<style lang="scss" scoped>
.page {
  min-height: 100vh;
  position: relative;
  padding-bottom: 48rpx;
  padding-left: 40rpx;
  padding-right: 40rpx;
  box-sizing: border-box;
}

.page--mid .bg-gradient {
  background: linear-gradient(165deg, #e8f4fc 0%, #d4e8f8 45%, #c5dff5 100%);
}
.page--mid .bg-dots {
  opacity: 0.45;
  background-image: radial-gradient(circle at 1px 1px, rgba(120, 160, 200, 0.35) 1px, transparent 0);
  background-size: 40rpx 40rpx;
}

.bg-wrap {
  position: fixed;
  inset: 0;
  z-index: 0;
}

.nav-bar {
  position: relative;
  z-index: 2;
  display: flex;
  align-items: center;
  justify-content: center; /* 居中整个导航栏的内容 */
  margin-bottom: 32rpx;
}
.nav-btn {
  position: absolute; /* 绝对定位到左侧，不影响标题居中 */
  left: 0;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.9);
  color: #5c534d;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 4rpx 16rpx rgba(180, 120, 100, 0.1);
  border: 2rpx solid rgba(200, 160, 140, 0.25);
}
.page--mid .nav-btn {
  border-color: rgba(140, 170, 210, 0.35);
  box-shadow: 0 4rpx 16rpx rgba(100, 140, 180, 0.12);
}
.nav-icon {
  font-size: 36rpx;
  line-height: 1;
}
.nav-center {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10rpx;
}
.nav-title {
  font-size: 36rpx;
  font-weight: 700;
  color: #3d3530;
  letter-spacing: 0.04em;
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
  color: #3d3530;
  font-variant-numeric: tabular-nums;
}

.card {
  position: relative;
  z-index: 2;
  margin-bottom: 32rpx;
  border-radius: 24rpx;
  overflow: hidden;
  box-shadow: 0 8rpx 28rpx rgba(180, 120, 100, 0.1), 0 2rpx 8rpx rgba(0,0,0,0.04);
  background: rgba(255, 255, 255, 0.95);
  border: 2rpx solid rgba(200, 160, 140, 0.15);
}
.card--mid {
  overflow: visible;
  border-radius: 28rpx;
  box-shadow: 0 12rpx 36rpx rgba(100, 140, 180, 0.14), 0 2rpx 10rpx rgba(0,0,0,0.05);
  border-color: rgba(180, 200, 230, 0.5);
}

.keyword-tab {
  position: absolute;
  top: 26rpx;
  right: 18rpx;
  z-index: 4;
  padding: 14rpx 20rpx;
  background: #d45d4a;
  border-radius: 14rpx;
  box-shadow: 0 10rpx 24rpx rgba(212, 93, 74, 0.25);
}
.keyword-tab-text {
  font-size: 26rpx;
  font-weight: 700;
  color: #fff;
}

.card-inner--stack {
  padding: 36rpx 28rpx 72rpx;
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
  height: 400rpx;
  border-radius: 20rpx;
  background: #f0f4f8;
}
.stack-placeholder {
  width: 100%;
  height: 220rpx;
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
  margin-top: -90rpx;
}

.answer-row {
  position: relative;
  z-index: 2;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 28rpx 24rpx;
  margin-bottom: 32rpx;
  background: rgba(255, 255, 255, 0.9);
  border-radius: 24rpx;
  border: 2rpx solid rgba(200, 160, 140, 0.2);
}
.answer-left {
  position: relative;
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
}
.finished-msg {
  font-size: 30rpx;
  font-weight: bold;
  color: #10b981;
}

.answer-mid-input-cover {
  position: absolute;
  inset: 0;
  opacity: 0.001;
  z-index: 3;
  color: transparent;
}
.answer-slots {
  position: relative;
  z-index: 1;
  display: flex;
  gap: 18rpx;
}
.slot {
  width: 76rpx;
  height: 76rpx;
  border: 2rpx dashed rgba(180, 140, 120, 0.4);
  border-radius: 20rpx;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 36rpx;
  font-weight: 600;
  background: rgba(255, 255, 255, 0.8);
}
.slot-char { line-height: 1; }
.slot-caret {
  width: 10rpx;
  height: 46rpx;
  background: rgba(80, 120, 200, 0.55);
  animation: caret-blink 1s steps(2, start) infinite;
}

@keyframes caret-blink {
  0%, 49% { opacity: 1; }
  50%, 100% { opacity: 0; }
}

.slot-error {
  border-color: #c04a38;
  border-style: solid;
  color: #c04a38;
  background: rgba(255, 220, 210, 0.5);
}
.slot-shake {
  animation: slot-shake 0.4s ease-in-out;
}
@keyframes slot-shake {
  0%, 100% { transform: translateX(0); }
  20% { transform: translateX(-8rpx); }
  40% { transform: translateX(8rpx); }
  60% { transform: translateX(-6rpx); }
  80% { transform: translateX(6rpx); }
}

.success-overlay, .result-overlay {
  position: fixed;
  inset: 0;
  z-index: 999;
  background: rgba(0, 0, 0, 0.6);
  display: flex;
  align-items: center;
  justify-content: center;
}
.success-content {
  display: flex;
  flex-direction: column;
  align-items: center;
  background: #fff;
  padding: 60rpx;
  border-radius: 40rpx;
}
.success-icon { font-size: 80rpx; }
.success-title { font-size: 40rpx; font-weight: bold; margin-top: 20rpx; }

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
  background: #3b82f6;
  color: #fff;
  border-radius: 100rpx;
  height: 88rpx;
  line-height: 88rpx;
}
</style>