<template>
  <view class="page">
    <view class="bg-wrap">
      <view class="bg-gradient" />
      <view class="bg-dots" />
      <view class="bg-glow" />
    </view>
    <PunPageNavBar
      :status-bar-height="statusBarHeight"
      status-bar-full-width
      :nav-bar-height="navBarHeight"
      :menu-button-height="menuButtonHeight"
      title="对战记录"
      @left-click="goBack"
    />

    <scroll-view scroll-y class="history-list" :style="{ height: listHeight + 'px' }" @scrolltolower="loadMore">
      <view v-if="list.length === 0" class="empty-state">
        <text class="empty-text">暂无对战记录</text>
      </view>
      
      <view v-for="item in list" :key="item.id" class="history-item">
        <view class="item-header">
          <text class="room-id">房间号：{{ item.roomId }}</text>
          <text class="date">{{ formatDate(item.createdAt) }}</text>
        </view>
        
        <view class="battle-info">
          <!-- 我 -->
          <view class="player my-player">
            <view class="avatar-wrap">
              <image v-if="userInfo?.avatar" :src="userInfo.avatar" class="avatar" mode="aspectFill" />
              <view v-else class="avatar placeholder">我</view>
              <view v-if="item.result === 'win'" class="crown">👑</view>
            </view>
            <text class="name">我</text>
          </view>

          <view class="vs-col">
            <text class="vs">VS</text>
            <text class="total-time">总耗时 {{ formatTime(item.totalTimeMs) }}</text>
          </view>

          <!-- 对手 -->
          <view class="player opponent-player">
            <view class="avatar-wrap">
              <image v-if="item.opponentAvatar" :src="item.opponentAvatar" class="avatar" mode="aspectFill" />
              <view v-else class="avatar placeholder">对</view>
              <view v-if="item.result === 'lose'" class="crown">👑</view>
            </view>
            <text class="name">{{ item.opponentName }}</text>
          </view>
        </view>
        
        <view class="result-banner" :class="item.result">
          {{ getResultText(item.result) }}
        </view>
      </view>
      
      <view v-if="loading" class="loading-more">加载中...</view>
      <view v-if="!hasMore && list.length > 0" class="no-more">没有更多记录了</view>
    </scroll-view>
  </view>
</template>

<script setup>
import { ref } from 'vue'
import { onLoad } from '@dcloudio/uni-app'
import { api } from '../../utils/api'
import { useNavBar } from '../../composables/useNavBar'
import PunPageNavBar from '../../components/PunPageNavBar.vue'
import { getUserInfo } from '../../utils/auth'
import { useWechatPageShare } from '../../composables/useWechatPageShare'

const { statusBarHeight, navBarHeight, menuButtonHeight } = useNavBar()

const shareRewardQuotaRef = ref(0)
// #ifdef MP-WEIXIN
useWechatPageShare('对战记录 · 谐音梗图', shareRewardQuotaRef)
// #endif
const userInfo = ref(getUserInfo())
const listHeight = ref(0)

const list = ref([])
const page = ref(1)
const hasMore = ref(true)
const loading = ref(false)

onLoad(() => {
  calcListHeight()
  loadData()
})

async function loadData() {
  if (loading.value || !hasMore.value) return
  loading.value = true
  
  try {
    const res = await api.getBattleHistory({ page: page.value, page_size: 20 })
    if (page.value === 1) {
      list.value = res.list
    } else {
      list.value.push(...res.list)
    }
    
    if (list.value.length >= res.total || res.list.length === 0) {
      hasMore.value = false
    } else {
      page.value++
    }
  } catch (err) {
    uni.showToast({ title: err.message || '加载失败', icon: 'none' })
  } finally {
    loading.value = false
  }
}

function loadMore() {
  loadData()
}
function goBack() {
  const pages = getCurrentPages()
  if (pages.length > 1) {
    uni.navigateBack()
  } else {
    uni.reLaunch({ url: '/pages/index/index' })
  }
}

function formatTime(ms) {
  if (!ms) return '--'
  const seconds = (ms / 1000).toFixed(1)
  return `${seconds}s`
}

function formatDate(dateStr) {
  if (dateStr == null || dateStr === '') return ''
  // iOS 无法解析 "YYYY-MM-DD HH:mm:ss"，需改为 ISO（空格换 T）
  let s = String(dateStr).trim()
  if (/^\d{4}-\d{2}-\d{2} \d/.test(s)) {
    s = s.replace(' ', 'T')
  }
  const date = typeof dateStr === 'number' ? new Date(dateStr) : new Date(s)
  if (Number.isNaN(date.getTime())) return ''
  return `${date.getMonth() + 1}-${date.getDate()} ${String(date.getHours()).padStart(2, '0')}:${String(date.getMinutes()).padStart(2, '0')}`
}

function getResultText(result) {
  if (result === 'win') return '胜利'
  if (result === 'lose') return '败北'
  return '平局'
}

function calcListHeight() {
  const { windowHeight = 0 } = uni.getSystemInfoSync() || {}
  const safeHeight = windowHeight - Number(statusBarHeight || 0) - Number(navBarHeight || 0)
  // 给底部留出一点空间，避免 iOS 滚动区域被裁切
  listHeight.value = Math.max(200, safeHeight - 16)
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

.history-list {
  flex: 1;
  box-sizing: border-box;
  z-index: 1;
  min-height: 200px;
}

.history-item {
  background: rgba(255, 255, 255, 0.88);
  backdrop-filter: blur(10px);
  -webkit-backdrop-filter: blur(10px);
  border-radius: 24rpx;
  padding: 30rpx;
  margin-bottom: 30rpx;
  box-shadow: 0 8rpx 24rpx rgba(169, 201, 238, 0.18);
  border: 2rpx solid rgba(169, 201, 238, 0.45);
  position: relative;
  overflow: hidden;
}

.item-header {
  display: flex;
  justify-content: space-between;
  margin-bottom: 30rpx;
  font-size: 24rpx;
  color: #64748b;
}

.battle-info {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20rpx;
}

.player {
  display: flex;
  flex-direction: column;
  align-items: center;
  flex: 1;
}

.avatar-wrap {
  position: relative;
  margin-bottom: 12rpx;
}

.avatar {
  width: 100rpx;
  height: 100rpx;
  border-radius: 50%;
  border: 4rpx solid #fff;
  box-shadow: 0 4rpx 12rpx rgba(0,0,0,0.1);
}

.placeholder {
  background: #e2e8f0;
  color: #64748b;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 32rpx;
  font-weight: bold;
}

.crown {
  position: absolute;
  top: -20rpx;
  right: -10rpx;
  font-size: 36rpx;
  filter: drop-shadow(0 4rpx 4rpx rgba(0,0,0,0.2));
  transform: rotate(15deg);
}

.name {
  font-size: 26rpx;
  font-weight: 600;
  color: #5a6d7a;
  margin-bottom: 8rpx;
  max-width: 160rpx;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.vs-col {
  display: flex;
  flex-direction: column;
  align-items: center;
  flex-shrink: 0;
  padding: 0 24rpx;
}

.vs {
  font-size: 40rpx;
  font-weight: 900;
  color: #94a3b8;
  font-style: italic;
  line-height: 1.2;
}

.total-time {
  margin-top: 12rpx;
  font-size: 24rpx;
  font-weight: 600;
  color: #64748b;
  white-space: nowrap;
}

.result-banner {
  position: absolute;
  top: 20rpx;
  right: -40rpx;
  background: #e2e8f0;
  color: #fff;
  font-size: 20rpx;
  font-weight: bold;
  padding: 4rpx 40rpx;
  transform: rotate(45deg);
  
  &.win {
    background: #10b981;
  }
  &.lose {
    background: #ef4444;
  }
  &.draw {
    background: #f59e0b;
  }
}

.empty-state {
  padding: 100rpx 0;
  text-align: center;
}

.empty-text {
  color: #94a3b8;
  font-size: 28rpx;
}

.loading-more, .no-more {
  text-align: center;
  padding: 30rpx 0;
  font-size: 24rpx;
  color: #94a3b8;
}
</style>