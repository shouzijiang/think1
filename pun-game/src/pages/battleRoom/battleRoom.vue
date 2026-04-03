<template>
  <view class="page">
    <view class="bg-wrap">
      <view class="bg-gradient" />
      <view class="bg-dots" />
      <view class="bg-glow" />
    </view>
    <view :style="{ height: statusBarHeight + 'px', width: '100%' }"></view>
    <view class="nav-bar" :style="{ height: navBarHeight + 'px' }">
      <view class="nav-btn" @click="goBack" :style="{ width: menuButtonHeight + 'px', height: menuButtonHeight + 'px' }">
        <text class="nav-icon">🏠</text>
      </view>
      <view class="nav-center">
        <text class="nav-title">对战大厅</text>
      </view>
    </view>

    <view class="content">
      <view class="room-card" v-if="roomId">
        <view class="room-header">
          <text class="room-title">房间号: {{ roomId }}</text>
          <text v-if="roleHint" class="role-hint">{{ roleHint }}</text>
        </view>
        
        <view class="players-area">
          <!-- 房主 -->
          <view class="player-box">
            <view class="avatar-wrap">
              <image v-if="creator?.avatar" :src="creator.avatar" class="avatar" mode="aspectFill" />
              <view v-else class="avatar placeholder">？</view>
              <view v-if="creatorReady" class="ready-badge">已准备</view>
            </view>
            <text class="name">{{ creator?.nickname || '等待加入...' }}</text>
          </view>
          
          <view class="vs-text">VS</view>
          
          <!-- 挑战者 -->
          <view class="player-box">
            <view class="avatar-wrap">
              <image v-if="challenger?.avatar" :src="challenger.avatar" class="avatar" mode="aspectFill" />
              <view v-else class="avatar placeholder">？</view>
              <view v-if="challengerReady" class="ready-badge">已准备</view>
            </view>
            <text class="name">{{ challenger?.nickname || '等待加入...' }}</text>
          </view>
        </view>

        <view class="action-area">
          <view v-if="isMyTurn" class="ready-block">
            <button
              v-if="!amIReady"
              class="btn-ready btn-ready--primary"
              hover-class="btn-ready--hover"
              @click="ready"
            >
              <view class="btn-ready-inner">
                <text class="btn-ready-icon">✓</text>
                <view class="btn-ready-texts">
                  <text class="btn-ready-title">准备游戏</text>
                  <text class="btn-ready-sub">双方准备完成后将自动开局</text>
                </view>
              </view>
            </button>
            <view v-else class="btn-ready btn-ready--waiting">
              <view class="btn-ready-inner">
                <text class="btn-ready-icon btn-ready-icon--pulse">⏳</text>
                <view class="btn-ready-texts">
                  <text class="btn-ready-title">等待对方准备</text>
                  <text class="btn-ready-sub">对方点击准备后即可开始</text>
                </view>
              </view>
            </view>
          </view>

          <button
            v-if="isCreator && !challenger"
            class="btn-share"
            open-type="share"
            hover-class="btn-share--hover"
          >
            <view class="btn-ready-inner">
              <text class="btn-share-icon">💬</text>
              <view class="btn-ready-texts">
                <text class="btn-share-title">邀请好友对战</text>
                <text class="btn-share-sub">分享到微信，好友打开即可加入</text>
              </view>
            </view>
          </button>
        </view>
      </view>

      <view v-else class="create-area">
        <view class="hero-icon">⚔️</view>
        <text class="desc">创建房间，邀请好友进行 1V1 谐音梗对战，5道题决胜负！</text>
        <button class="btn-create" @click="createRoom" :loading="creating">创建对战房间</button>
        <button class="btn-history-big" @click="goHistory">
          历史对战记录
        </button>
      </view>
    </view>

    <!-- 分享链接进房：等待 join 成功 -->
    <view v-if="shareJoinLoading" class="join-loading-mask" @touchmove.stop.prevent>
      <view class="join-loading-inner">
        <text class="join-loading-text">正在加入房间…</text>
      </view>
    </view>
  </view>
</template>

<script setup>
import { ref, computed } from 'vue'
import { onLoad, onUnload, onShow, onShareAppMessage, onShareTimeline } from '@dcloudio/uni-app'
import { api } from '../../utils/api'
import { wsApi } from '../../utils/ws'
import { useNavBar } from '../../composables/useNavBar'
import { getUserInfo, wechatLogin } from '../../utils/auth'

const { statusBarHeight, navBarHeight, menuButtonHeight } = useNavBar()

const roomId = ref('')
const creating = ref(false)
/** 仅分享带 roomId 进入时：等待 WS 返回房间状态 */
const shareJoinLoading = ref(false)
const myUserId = ref(null)
const myUserInfo = ref(getUserInfo())

const creator = ref(null)
const challenger = ref(null)
/** 来自 DB/WS，断线重连时即使连接对象为空也能判断身份 */
const roomCreatorId = ref(0)
const roomChallengerId = ref(null)
const creatorReady = ref(false)
const challengerReady = ref(false)
let keepWsAliveForBattle = false
let joinedRoomId = ''
let joinRetryTimer = null
let joinRetryCount = 0
let hasReceivedRoomInfo = false
const JOIN_RETRY_DELAY = 1500
const MAX_JOIN_RETRY = 1

// 分享进房/重连时序兜底
let battleRedirecting = false
let joinWaitTimeout = null
let joinWaitRetryCount = 0
const JOIN_WAIT_TIMEOUT_MS = 8000
const MAX_JOIN_WAIT_RETRY = 2

function normalizeUserId(v) {
  const n = Number(v)
  return Number.isFinite(n) ? n : 0
}

const isCreator = computed(() => {
  const me = normalizeUserId(myUserId.value)
  if (!me) return false
  const rid = normalizeUserId(roomCreatorId.value)
  if (rid && me === rid) return true
  return !!(creator.value && normalizeUserId(creator.value.id) === me)
})
const isChallenger = computed(() => {
  const me = normalizeUserId(myUserId.value)
  if (!me) return false
  const hid = roomChallengerId.value
  if (hid != null && hid !== '') {
    return me === normalizeUserId(hid)
  }
  return !!(challenger.value && normalizeUserId(challenger.value.id) === me)
})
const isMyTurn = computed(() => isCreator.value || isChallenger.value)
const amIReady = computed(() => (isCreator.value && creatorReady.value) || (isChallenger.value && challengerReady.value))

const roleHint = computed(() => {
  if (!roomId.value || !normalizeUserId(myUserId.value)) return ''
  if (isCreator.value) return '你是房主'
  if (isChallenger.value) return '你是挑战者'
  return ''
})

onLoad((options) => {
  initRoomPage(options)
})

onShow(() => {
  // 分享进房时：首次 join 还在等待 WS 返回，不要重复触发 rejoin，避免状态错乱
  if (shareJoinLoading.value) return
  rejoinCurrentRoomIfNeeded()
})

onUnload(() => {
  shareJoinLoading.value = false
  if (joinRetryTimer) {
    clearTimeout(joinRetryTimer)
    joinRetryTimer = null
  }
  if (joinWaitTimeout) {
    clearTimeout(joinWaitTimeout)
    joinWaitTimeout = null
  }
  joinWaitRetryCount = 0
  battleRedirecting = false
  wsApi.off('room_info')
  wsApi.off('start_game')
  wsApi.off('resume_game')
  wsApi.off('game_over')
  wsApi.off('error')
  if (!keepWsAliveForBattle) {
    wsApi.close()
  }
})

onShareAppMessage(() => {
  return {
    title: '来和我1V1对战谐音梗图吧！',
    path: `/pages/battleRoom/battleRoom?roomId=${roomId.value}`
  }
})

onShareTimeline(() => {
  const rid = roomId.value
  return {
    title: '来和我1V1对战谐音梗图吧！',
    query: rid ? `roomId=${encodeURIComponent(rid)}` : '',
  }
})

function endShareJoinLoading() {
  shareJoinLoading.value = false
  // 停止一切 join 兜底循环，避免 start_game/resume_game 之后又重复发送 join
  hasReceivedRoomInfo = true
  if (joinRetryTimer) {
    clearTimeout(joinRetryTimer)
    joinRetryTimer = null
  }
  joinRetryCount = 0
  if (joinWaitTimeout) {
    clearTimeout(joinWaitTimeout)
    joinWaitTimeout = null
  }
  joinWaitRetryCount = 0
}

function setupWsListeners() {
  wsApi.on('room_info', (data) => {
    endShareJoinLoading()
    if (joinRetryTimer) {
      clearTimeout(joinRetryTimer)
      joinRetryTimer = null
    }
    joinRetryCount = 0
    hasReceivedRoomInfo = true
    if (data.creatorId != null) roomCreatorId.value = normalizeUserId(data.creatorId)
    if (data.challengerId !== undefined) {
      roomChallengerId.value = data.challengerId == null || data.challengerId === ''
        ? null
        : normalizeUserId(data.challengerId)
    }
    creator.value = data.creator
    challenger.value = data.challenger
    creatorReady.value = data.creatorReady
    challengerReady.value = data.challengerReady
  })

  wsApi.on('start_game', (data) => {
    if (battleRedirecting) return
    battleRedirecting = true
    endShareJoinLoading()
    uni.showToast({ title: '游戏开始！', icon: 'none' })
    const levelsStr = JSON.stringify(data.levels)
    keepWsAliveForBattle = true
    setTimeout(() => {
      // 保留房间连接，跳转到游戏页
      uni.redirectTo({
        url: `/pages/battlePlay/battlePlay?roomId=${roomId.value}&levels=${levelsStr}&myUserId=${myUserId.value}&myName=${encodeURIComponent(data.myName)}&opponentName=${encodeURIComponent(data.opponentName)}`
      })
    }, 1000)
  })

  wsApi.on('resume_game', (data) => {
    if (battleRedirecting) return
    battleRedirecting = true
    endShareJoinLoading()
    uni.showToast({ title: '正在恢复对战...', icon: 'none' })
    const levelsStr = JSON.stringify(data.levels)
    keepWsAliveForBattle = true
    setTimeout(() => {
      uni.redirectTo({
        url: `/pages/battlePlay/battlePlay?roomId=${roomId.value}&levels=${levelsStr}&resume=1&myUserId=${myUserId.value}&myProgress=${data.myProgress}&opponentProgress=${data.opponentProgress}&timePassed=${data.timePassed}&myName=${encodeURIComponent(data.myName)}&opponentName=${encodeURIComponent(data.opponentName)}`
      })
    }, 500)
  })

  // 如果断线期间游戏已经结束，服务端会在 join 后直接发送 game_over
  wsApi.on('game_over', (data) => {
    endShareJoinLoading()
    // 游戏已结束，直接跳去历史记录
    uni.showToast({ title: '对战已结束', icon: 'none' })
    setTimeout(() => {
      uni.redirectTo({ url: '/pages/battleHistory/battleHistory' })
    }, 1000)
  })

  wsApi.on('error', (data) => {
    endShareJoinLoading()
    uni.showToast({ title: data.msg, icon: 'none' })
    if (data.msg === '房间不存在或已解散~') {
      setTimeout(() => {
        roomId.value = ''
        // 如果是通过链接进来的，清除链接上的参数防止刷新又进去
        uni.redirectTo({ url: '/pages/battleRoom/battleRoom' })
      }, 1500)
    }
  })
}

async function createRoom() {
  if (creating.value) return
  creating.value = true
  try {
    const uid = normalizeUserId(
      myUserId.value || myUserInfo.value?.id || myUserInfo.value?.user_id
    )
    // 乐观更新：立刻把自己设置为房主，减少等待延迟感
    creator.value = {
      id: uid,
      nickname: myUserInfo.value?.nickname || '我',
      avatar: myUserInfo.value?.avatar || ''
    }
    roomCreatorId.value = uid
    roomChallengerId.value = null

    const res = await api.createBattleRoom()
    roomId.value = res.roomId
    joinRoom(res.roomId)
  } catch (err) {
    uni.showToast({ title: err.message || '创建失败', icon: 'none' })
    creator.value = null // 回滚
    roomCreatorId.value = 0
    roomChallengerId.value = null
  } finally {
    creating.value = false
  }
}

function joinRoom(id) {
  if (!id) return
  // 允许同一房间重复 join：退后台断线后需重新绑定连接，服务端按 userId 恢复角色
  joinedRoomId = id
  if (joinRetryTimer) {
    clearTimeout(joinRetryTimer)
    joinRetryTimer = null
  }
  joinRetryCount = 0
  hasReceivedRoomInfo = false
  wsApi.send({ action: 'join', roomId: id })
  scheduleJoinRetry(id)
  if (shareJoinLoading.value) startJoinWaitTimeout(id)
}

function scheduleJoinRetry(id) {
  if (joinRetryCount >= MAX_JOIN_RETRY) return
  joinRetryTimer = setTimeout(() => {
    // 仍在等待同一房间信息时，重发一次 join 兜底
    if (joinedRoomId === id && !hasReceivedRoomInfo) {
      joinRetryCount++
      wsApi.send({ action: 'join', roomId: id })
    }
  }, JOIN_RETRY_DELAY)
}

function startJoinWaitTimeout(id) {
  if (!shareJoinLoading.value) return
  if (joinWaitTimeout) {
    clearTimeout(joinWaitTimeout)
    joinWaitTimeout = null
  }

  joinWaitTimeout = setTimeout(() => {
    if (joinedRoomId !== id) return
    joinWaitTimeout = null

    if (joinWaitRetryCount >= MAX_JOIN_WAIT_RETRY) {
      uni.showToast({ title: '加入房间超时，请稍后再试', icon: 'none' })
      return
    }

    joinWaitRetryCount++

    // 清理当前 join 重试兜底，再触发一次重连 join
    if (joinRetryTimer) {
      clearTimeout(joinRetryTimer)
      joinRetryTimer = null
    }
    joinRetryCount = 0

    rejoinCurrentRoomIfNeeded()
  }, JOIN_WAIT_TIMEOUT_MS)
}

async function rejoinCurrentRoomIfNeeded() {
  if (!roomId.value) return
  try {
    const token = uni.getStorageSync('token')
    if (!token) return
    const local = getUserInfo()
    if (local) {
      myUserId.value = normalizeUserId(local.id ?? local.user_id)
    }
    const res = await wsApi.connect(token)
    if (res && res.userInfo && res.userInfo.id) {
      myUserId.value = normalizeUserId(res.userInfo.id)
    }
    joinRoom(roomId.value)
  } catch (e) {
    console.warn('rejoin room failed', e)
  }
}

async function initRoomPage(options) {
  // 监听 WebSocket 事件
  setupWsListeners()
  try {
    let token = uni.getStorageSync('token')
    if (!token) {
      await wechatLogin()
      token = uni.getStorageSync('token')
    }
    if (!token) {
      throw new Error('登录失败')
    }

    const localUser = getUserInfo()
    if (localUser) {
      myUserId.value = normalizeUserId(localUser.id ?? localUser.user_id)
    }

    const res = await wsApi.connect(token)
    if (res && res.userInfo && res.userInfo.id) {
      myUserId.value = normalizeUserId(res.userInfo.id)
    }

    // 如果是通过分享链接进来的，自动加入房间
    if (options.roomId) {
      shareJoinLoading.value = true
      roomId.value = options.roomId
      joinRoom(options.roomId)
    }
  } catch (err) {
    endShareJoinLoading()
    uni.showToast({ title: err.message || '连接服务器失败', icon: 'none' })
    setTimeout(() => uni.navigateBack(), 1200)
  }
}

function ready() {
  wsApi.send({ action: 'ready' })
}
function goBack() {
  const pages = getCurrentPages()
  if (pages.length > 1) {
    uni.navigateBack()
  } else {
    uni.reLaunch({ url: '/pages/index/index' })
  }
}

function goHistory() {
  uni.navigateTo({ url: '/pages/battleHistory/battleHistory' })
}
</script>

<style lang="scss" scoped>
@use '../../styles/page-theme.scss' as *;

.page {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  position: relative;
  padding: 0 40rpx;
  @include pt-page-background;
}

.btn-history {
  position: absolute;
  right: 0;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.92);
  color: #5a6d7a;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 4rpx 14rpx rgba(169, 201, 238, 0.22);
  border: 2rpx solid rgba(169, 201, 238, 0.65);
}
.history-icon {
  font-size: 36rpx;
  line-height: 1;
}

.content {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  // justify-content: center;
  padding: 40rpx;
  position: relative;
  z-index: 1;
}

.create-area {
  flex: 1;
  width: 100%;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  text-align: center;
}
.hero-icon {
  font-size: 120rpx;
  margin-bottom: 40rpx;
  filter: drop-shadow(0 10rpx 20rpx rgba(0,0,0,0.1));
}
.desc {
  font-size: 28rpx;
  color: #64748b;
  margin-bottom: 60rpx;
  line-height: 1.5;
  width: 80%;
}
.btn-create {
  background: linear-gradient(135deg, #a8e6a2 0%, #91d58b 100%);
  color: #fff;
  border-radius: 100rpx;
  padding: 0 80rpx;
  height: 96rpx;
  line-height: 96rpx;
  font-size: 32rpx;
  font-weight: bold;
  box-shadow: 0 16rpx 32rpx rgba(111, 184, 104, 0.28);
  margin-bottom: 30rpx;
  &::after { border: none; }
}
.btn-history-big {
  background: #fff;
  color: #5a6d7a;
  border-radius: 100rpx;
  padding: 0 80rpx;
  height: 96rpx;
  line-height: 92rpx;
  font-size: 32rpx;
  font-weight: bold;
  border: 4rpx solid rgba(169, 201, 238, 0.55);
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 12rpx;
  box-shadow: 0 8rpx 20rpx rgba(169, 201, 238, 0.12);
  &::after { border: none; }
}

.room-card {
  width: 100%;
  background: rgba(255, 255, 255, 0.88);
  backdrop-filter: blur(10px);
  border-radius: 40rpx;
  padding: 60rpx 40rpx;
  box-shadow: 0 20rpx 40rpx rgba(169, 201, 238, 0.2);
  border: 4rpx solid rgba(169, 201, 238, 0.4);
}

.room-header {
  text-align: center;
  margin-bottom: 60rpx;
}
.room-title {
  font-size: 32rpx;
  font-weight: 800;
  color: #5a6d7a;
  background: rgba(234, 246, 249, 0.95);
  padding: 12rpx 32rpx;
  border-radius: 100rpx;
  border: 2rpx solid rgba(169, 201, 238, 0.35);
}
.role-hint {
  display: block;
  margin-top: 16rpx;
  font-size: 24rpx;
  font-weight: 600;
  color: #64748b;
  text-align: center;
}

.players-area {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 80rpx;
}
.player-box {
  display: flex;
  flex-direction: column;
  align-items: center;
  flex: 1;
}
.avatar-wrap {
  position: relative;
  margin-bottom: 20rpx;
}
.avatar {
  width: 140rpx;
  height: 140rpx;
  border-radius: 50%;
  border: 6rpx solid #fff;
  box-shadow: 0 8rpx 16rpx rgba(0,0,0,0.1);
}
.placeholder {
  background: #e2e8f0;
  color: #94a3b8;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 48rpx;
  font-weight: bold;
}
.ready-badge {
  position: absolute;
  bottom: -10rpx;
  left: 50%;
  transform: translateX(-50%);
  background: #10b981;
  color: #fff;
  font-size: 20rpx;
  padding: 4rpx 16rpx;
  border-radius: 20rpx;
  border: 2rpx solid #fff;
  white-space: nowrap;
}
.name {
  font-size: 28rpx;
  font-weight: 600;
  color: #475569;
}
.vs-text {
  font-size: 48rpx;
  font-weight: 900;
  color: #cbd5e1;
  font-style: italic;
  padding: 0 40rpx;
}

.action-area {
  display: flex;
  flex-direction: column;
  gap: 20rpx;
  margin-top: 8rpx;
}

.ready-block {
  width: 100%;
  display: flex;
  flex-direction: column;
  align-items: center;
}

.btn-ready {
  width: 100%;
  border-radius: 28rpx;
  overflow: hidden;
  &::after {
    border: none;
  }
}

.btn-ready-inner {
  display: flex;
  align-items: center;
  gap: 24rpx;
  padding: 28rpx 32rpx;
  text-align: left;
}

/* 「准备」略小于下方「邀请」，区分主次（邀请全宽更醒目） */
.ready-block .btn-ready {
  width: 100%;
  max-width: 600rpx;
  border-radius: 24rpx;
}

.ready-block .btn-ready-inner {
  padding: 24rpx 28rpx;
  gap: 20rpx;
}

.ready-block .btn-ready-title {
  font-size: 30rpx;
}

.ready-block .btn-ready-sub {
  font-size: 22rpx;
}

.ready-block .btn-ready-icon {
  width: 64rpx;
  height: 64rpx;
  border-radius: 18rpx;
  font-size: 32rpx;
}

.btn-ready-texts {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 8rpx;
  min-width: 0;
}

.btn-ready-title {
  font-size: 34rpx;
  font-weight: 800;
  letter-spacing: 0.02em;
  line-height: 1.25;
}

.btn-ready-sub {
  font-size: 24rpx;
  font-weight: 500;
  line-height: 1.4;
  opacity: 0.88;
}

.btn-ready-icon {
  flex-shrink: 0;
  width: 72rpx;
  height: 72rpx;
  border-radius: 20rpx;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 36rpx;
  line-height: 1;
  background: rgba(255, 255, 255, 0.22);
}

.btn-ready--primary {
  background: linear-gradient(145deg, #34d399 0%, #10b981 42%, #059669 100%);
  color: #fff;
  box-shadow:
    0 12rpx 28rpx rgba(16, 185, 129, 0.35),
    inset 0 1rpx 0 rgba(255, 255, 255, 0.25);
}

.btn-ready--hover {
  opacity: 0.92;
  transform: scale(0.99);
}

.btn-ready--waiting {
  background: linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%);
  border: 2rpx solid #e2e8f0;
  box-shadow: inset 0 1rpx 0 rgba(255, 255, 255, 0.9);
}

.btn-ready--waiting .btn-ready-title {
  color: #475569;
}

.btn-ready--waiting .btn-ready-sub {
  color: #94a3b8;
  opacity: 1;
}

.btn-ready--waiting .btn-ready-icon {
  background: #e2e8f0;
  color: #64748b;
}

.btn-ready-icon--pulse {
  animation: ready-pulse 1.6s ease-in-out infinite;
}

@keyframes ready-pulse {
  0%,
  100% {
    opacity: 1;
  }
  50% {
    opacity: 0.55;
  }
}

/* 「邀请」全宽、略大于上方准备，作为主引导 */
.btn-share {
  width: 100%;
  padding: 0;
  border-radius: 28rpx;
  overflow: hidden;
  background: linear-gradient(180deg, #ffffff 0%, #f0fdf4 100%);
  border: 2rpx solid rgba(16, 185, 129, 0.35);
  margin-top: 20rpx;
  box-shadow: 0 10rpx 24rpx rgba(16, 185, 129, 0.1);
  &::after {
    border: none;
  }
}

.btn-share .btn-ready-inner {
  padding: 28rpx 32rpx;
  gap: 24rpx;
}

.btn-share-icon {
  flex-shrink: 0;
  width: 72rpx;
  height: 72rpx;
  border-radius: 20rpx;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 36rpx;
  line-height: 1;
  background: linear-gradient(145deg, #d1fae5 0%, #a7f3d0 100%);
  border: 2rpx solid rgba(16, 185, 129, 0.25);
}

.btn-share-title {
  font-size: 32rpx;
  font-weight: 800;
  letter-spacing: 0.02em;
  line-height: 1.25;
  color: #047857;
  text-align: left;
}

.btn-share-sub {
  font-size: 24rpx;
  font-weight: 500;
  line-height: 1.4;
  color: #64748b;
  text-align: left;
}

.btn-share--hover {
  background: linear-gradient(180deg, #ecfdf5 0%, #d1fae5 100%);
  border-color: rgba(16, 185, 129, 0.5);
}

.join-loading-mask {
  position: fixed;
  inset: 0;
  z-index: 999;
  background: rgba(15, 23, 42, 0.45);
  display: flex;
  align-items: center;
  justify-content: center;
}
.join-loading-inner {
  background: rgba(255, 255, 255, 0.96);
  padding: 40rpx 56rpx;
  border-radius: 24rpx;
  box-shadow: 0 16rpx 48rpx rgba(0, 0, 0, 0.12);
}
.join-loading-text {
  font-size: 28rpx;
  color: #334155;
  font-weight: 600;
}
</style>