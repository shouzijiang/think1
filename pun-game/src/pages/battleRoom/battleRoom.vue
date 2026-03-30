<template>
  <view class="page">
    <view class="bg-wrap">
      <view class="bg-gradient" />
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
          <button v-if="isMyTurn && !amIReady" class="btn-ready" @click="ready">准备</button>
          <button v-if="isMyTurn && amIReady" class="btn-ready disabled" disabled>等待对方</button>
          
          <!-- 分享给微信好友的大按钮，在挑战者未加入时显示 -->
          <button v-if="isCreator && !challenger" class="btn-share" open-type="share">
            <text class="share-icon">💬</text> 发送给微信好友
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
  </view>
</template>

<script setup>
import { ref, computed } from 'vue'
import { onLoad, onUnload, onShareAppMessage } from '@dcloudio/uni-app'
import { api } from '../../utils/api'
import { wsApi } from '../../utils/ws'
import { useNavBar } from '../../composables/useNavBar'
import { getUserInfo } from '../../utils/auth'

const { statusBarHeight, navBarHeight, menuButtonHeight } = useNavBar()

const roomId = ref('')
const creating = ref(false)
const myUserId = ref(null)
const myUserInfo = ref(getUserInfo())

const creator = ref(null)
const challenger = ref(null)
const creatorReady = ref(false)
const challengerReady = ref(false)

const isCreator = computed(() => creator.value && creator.value.id === myUserId.value)
const isChallenger = computed(() => challenger.value && challenger.value.id === myUserId.value)
const isMyTurn = computed(() => isCreator.value || isChallenger.value)
const amIReady = computed(() => (isCreator.value && creatorReady.value) || (isChallenger.value && challengerReady.value))

onLoad((options) => {
  const token = uni.getStorageSync('token')
  if (!token) {
    uni.showToast({ title: '请先登录', icon: 'none' })
    setTimeout(() => uni.navigateBack(), 1500)
    return
  }

  // 监听 WebSocket 事件
  setupWsListeners()

  // 连接 WS
  wsApi.connect(token).then((res) => {
    myUserId.value = res.userInfo.id
    
    // 如果是通过分享链接进来的，自动加入房间
    if (options.roomId) {
      roomId.value = options.roomId
      joinRoom(options.roomId)
    }
  }).catch(err => {
    uni.showToast({ title: '连接服务器失败', icon: 'none' })
  })
})

onUnload(() => {
  wsApi.off('room_info')
  wsApi.off('start_game')
  wsApi.off('resume_game')
  wsApi.off('game_over')
  wsApi.off('error')
  wsApi.close()
})

onShareAppMessage(() => {
  return {
    title: '来和我1V1对战谐音梗图吧！',
    path: `/pages/battleRoom/battleRoom?roomId=${roomId.value}`
  }
})

function setupWsListeners() {
  wsApi.on('room_info', (data) => {
    creator.value = data.creator
    challenger.value = data.challenger
    creatorReady.value = data.creatorReady
    challengerReady.value = data.challengerReady
  })

  wsApi.on('start_game', (data) => {
    uni.showToast({ title: '游戏开始！', icon: 'none' })
    const levelsStr = JSON.stringify(data.levels)
    setTimeout(() => {
      // 保留房间连接，跳转到游戏页
      uni.redirectTo({
        url: `/pages/battlePlay/battlePlay?roomId=${roomId.value}&levels=${levelsStr}&myName=${encodeURIComponent(data.myName)}&opponentName=${encodeURIComponent(data.opponentName)}`
      })
    }, 1000)
  })

  wsApi.on('resume_game', (data) => {
    uni.showToast({ title: '正在恢复对战...', icon: 'none' })
    const levelsStr = JSON.stringify(data.levels)
    setTimeout(() => {
      uni.redirectTo({
        url: `/pages/battlePlay/battlePlay?roomId=${roomId.value}&levels=${levelsStr}&resume=1&myProgress=${data.myProgress}&opponentProgress=${data.opponentProgress}&timePassed=${data.timePassed}&myName=${encodeURIComponent(data.myName)}&opponentName=${encodeURIComponent(data.opponentName)}`
      })
    }, 500)
  })

  // 如果断线期间游戏已经结束，服务端会在 join 后直接发送 game_over
  wsApi.on('game_over', (data) => {
    // 游戏已结束，直接跳去历史记录
    uni.showToast({ title: '对战已结束', icon: 'none' })
    setTimeout(() => {
      uni.redirectTo({ url: '/pages/battleHistory/battleHistory' })
    }, 1000)
  })

  wsApi.on('error', (data) => {
    uni.showToast({ title: data.msg, icon: 'none' })
    if (data.msg === 'Room not found or already ended' || data.msg === '房间不存在或已解散') {
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
    // 乐观更新：立刻把自己设置为房主，减少等待延迟感
    creator.value = {
      id: myUserId.value,
      nickname: myUserInfo.value?.nickname || '我',
      avatar: myUserInfo.value?.avatar || ''
    }

    const res = await api.createBattleRoom()
    roomId.value = res.roomId
    joinRoom(res.roomId)
  } catch (err) {
    uni.showToast({ title: err.message || '创建失败', icon: 'none' })
    creator.value = null // 回滚
  } finally {
    creating.value = false
  }
}

function joinRoom(id) {
  wsApi.send({ action: 'join', roomId: id })
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
.page {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  position: relative;
  padding: 0 40rpx
}

.bg-wrap {
  position: fixed;
  inset: 0;
  z-index: 0;
}
.bg-gradient {
  position: absolute;
  inset: 0;
  background: linear-gradient(165deg, #f0f7ff 0%, #e0eafd 100%);
}

.nav-bar {
  display: flex;
  align-items: center;
  justify-content: center;
  position: relative;
  z-index: 2;
  width: 100%;
  margin-bottom: 32rpx;
}
.nav-btn {
  position: absolute;
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
.btn-history {
  position: absolute;
  right: 0;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.9);
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 4rpx 16rpx rgba(180, 120, 100, 0.1);
  border: 2rpx solid rgba(200, 160, 140, 0.25);
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
  justify-content: center;
  padding: 40rpx;
  position: relative;
  z-index: 1;
}

.create-area {
  display: flex;
  flex-direction: column;
  align-items: center;
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
  background: linear-gradient(135deg, #ff7a63 0%, #e04a35 100%);
  color: #fff;
  border-radius: 100rpx;
  padding: 0 80rpx;
  height: 96rpx;
  line-height: 96rpx;
  font-size: 32rpx;
  font-weight: bold;
  box-shadow: 0 16rpx 32rpx rgba(224, 74, 53, 0.3);
  margin-bottom: 30rpx;
  &::after { border: none; }
}
.btn-history-big {
  background: #fff;
  color: #475569;
  border-radius: 100rpx;
  padding: 0 80rpx;
  height: 96rpx;
  line-height: 92rpx;
  font-size: 32rpx;
  font-weight: bold;
  border: 4rpx solid #cbd5e1;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 12rpx;
  box-shadow: 0 8rpx 16rpx rgba(0, 0, 0, 0.05);
  &::after { border: none; }
}

.room-card {
  width: 100%;
  background: rgba(255, 255, 255, 0.85);
  backdrop-filter: blur(10px);
  border-radius: 40rpx;
  padding: 60rpx 40rpx;
  box-shadow: 0 20rpx 40rpx rgba(100, 140, 200, 0.15);
  border: 4rpx solid rgba(255, 255, 255, 0.6);
}

.room-header {
  text-align: center;
  margin-bottom: 60rpx;
}
.room-title {
  font-size: 32rpx;
  font-weight: 800;
  color: #1e293b;
  background: #f1f5f9;
  padding: 12rpx 32rpx;
  border-radius: 100rpx;
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
  gap: 24rpx;
}
.btn-ready {
  background: linear-gradient(135deg, #10b981 0%, #059669 100%);
  color: #fff;
  border-radius: 100rpx;
  height: 96rpx;
  line-height: 96rpx;
  font-size: 32rpx;
  font-weight: bold;
  box-shadow: 0 10rpx 20rpx rgba(16, 185, 129, 0.3);
  &::after { border: none; }
  &.disabled {
    background: #cbd5e1;
    box-shadow: none;
    color: #94a3b8;
  }
}
.btn-share {
  background: #07c160; /* 微信绿 */
  color: #fff;
  border-radius: 100rpx;
  height: 96rpx;
  line-height: 96rpx;
  font-size: 32rpx;
  font-weight: bold;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 12rpx;
  box-shadow: 0 10rpx 20rpx rgba(7, 193, 96, 0.3);
  &::after { border: none; }
}
.share-icon {
  font-size: 40rpx;
  line-height: 1;
}
</style>