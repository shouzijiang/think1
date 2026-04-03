<template>
  <view class="page">
    <view class="bg-wrap">
      <view class="bg-gradient" />
      <view class="bg-dots" />
      <view class="bg-glow" />
    </view>

    <!-- 顶部状态栏占位 -->
    <view :style="{ height: statusBarHeight + 'px' }"></view>

    <view class="nav-bar" :style="{ height: navBarHeight + 'px' }">
      <view class="nav-btn" @click="back" :style="{ width: menuButtonHeight + 'px', height: menuButtonHeight + 'px' }">
        <text class="nav-icon">🏠</text>
      </view>
      <view class="nav-center">
        <text class="nav-title">排行榜</text>
      </view>
      <!-- <view class="nav-btn nav-btn-refresh" @click="refresh">
        <text class="nav-icon">↻</text>
      </view> -->
    </view>

    <view class="tabs-wrap">
      <view class="tabs">
        <view :class="['tab', { active: currentTab === 'mid' }]" @click="switchTab('mid')">画中寻梗榜</view>
        <view :class="['tab', { active: currentTab === 'beginner' }]" @click="switchTab('beginner')">梗图填词榜</view>
      </view>
    </view>

    <scroll-view v-if="!loading" class="list" scroll-y>
      <view
        v-for="(item, index) in list"
        :key="item.user_id"
        :class="['item', { 'item-top3': index < 3 }]"
      >
        <view class="item-left">
          <view :class="['rank-badge', { 'rank-badge-top': index < 3 }]">
            <text v-if="index === 0" class="rank-icon">👑</text>
            <text v-else-if="index === 1" class="rank-icon">🥈</text>
            <text v-else-if="index === 2" class="rank-icon">🥉</text>
            <text v-else class="rank-num">{{ index + 1 }}</text>
          </view>
          <image v-if="item.avatar" class="avatar-img" :src="item.avatar" mode="aspectFill" />
          <view v-else class="avatar-fallback">👤</view>
          <view class="info">
            <text class="name">{{ item.nickname || '用户' }}</text>
            <text class="time">{{ item.updated_at ? '最近: ' + item.updated_at : '' }}</text>
          </view>
        </view>
        <view class="item-right">
          <text class="level-num">{{ item.max_level }}</text>
          <text class="level-unit">关</text>
        </view>
      </view>
    </scroll-view>
    <view v-else class="list list--loading">
      <text class="loading-text">加载中…</text>
    </view>
  </view>
</template>

<script setup>
import { ref } from 'vue'
import { onShow } from '@dcloudio/uni-app'
import { api } from '../../utils/api'
import { useNavBar } from '../../composables/useNavBar'
import { useWechatPageShare } from '../../composables/useWechatPageShare'

const { statusBarHeight, navBarHeight, menuButtonHeight } = useNavBar()

// #ifdef MP-WEIXIN
useWechatPageShare('排行榜 · 谐音梗图')
// #endif

const list = ref([])
const loading = ref(false)
const currentTab = ref('mid')

function loadRank() {
  loading.value = true
  const params = { page: 1, page_size: 100 }
  if (currentTab.value === 'mid') {
    params.gameTier = 'mid'
  }
  api.getRankList(params)
    .then((data) => {
      list.value = (data.list || []).map((item) => ({
        user_id: item.user_id,
        nickname: item.nickname,
        avatar: item.avatar,
        max_level: item.max_level,
        updated_at: item.updated_at || '',
      }))
    })
    .catch(() => {
      list.value = []
    })
    .finally(() => {
      loading.value = false
    })
}

onShow(() => loadRank())

function back() {
  uni.reLaunch({ url: '/pages/index/index' })
}
function refresh() {
  loadRank()
  uni.showToast({ title: '已刷新', icon: 'none' })
}

function switchTab(tab) {
  if (currentTab.value === tab) return
  currentTab.value = tab
  list.value = []
  loadRank()
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
  @include pt-page-background;
}

.tabs-wrap {
  position: relative;
  z-index: 2;
  display: flex;
  justify-content: center;
  margin-bottom: 30rpx;
}
.tabs {
  display: flex;
  background: rgba(255, 255, 255, 0.75);
  border-radius: 40rpx;
  padding: 8rpx;
  box-shadow: 0 4rpx 14rpx rgba(169, 201, 238, 0.18);
  border: 2rpx solid rgba(169, 201, 238, 0.45);
}
.tab {
  padding: 12rpx 40rpx;
  font-size: 28rpx;
  font-weight: 500;
  color: #8eadcf;
  border-radius: 32rpx;
  transition: all 0.3s ease;
}
.tab.active {
  background: linear-gradient(145deg, #a8e6a2 0%, #91d58b 100%);
  color: #ffffff;
  box-shadow: 0 4rpx 14rpx rgba(111, 184, 104, 0.28);
}

.list {
  position: relative;
  z-index: 2;
  height: calc(100vh - 380rpx);
  box-sizing: border-box;
  .uni-scroll-view {
    width: 100%;
  }
}
.list--loading {
  display: flex;
  align-items: center;
  justify-content: center;
}
.loading-text {
  font-size: 28rpx;
  color: #8eadcf;
}
.item {
  background: rgba(255, 255, 255, 0.92);
  border-radius: 22rpx;
  padding: 26rpx 28rpx;
  margin-bottom: 18rpx;
  display: flex;
  align-items: center;
  justify-content: space-between;
  box-shadow: 0 4rpx 16rpx rgba(169, 201, 238, 0.12);
  border: 2rpx solid rgba(169, 201, 238, 0.4);
}
.item-top3 {
  border-color: rgba(145, 213, 139, 0.55);
  box-shadow: 0 6rpx 20rpx rgba(111, 184, 104, 0.15);
}
.item-left {
  display: flex;
  align-items: center;
  gap: 22rpx;
}
.rank-badge {
  width: 56rpx;
  text-align: center;
}
.rank-badge-top {
  min-width: 56rpx;
}
.rank-icon { font-size: 42rpx; }
.rank-num {
  font-size: 30rpx;
  font-weight: 700;
  color: #8eadcf;
}
.avatar-img,
.avatar-fallback {
  width: 80rpx;
  height: 80rpx;
  border-radius: 50%;
  background: rgba(234, 246, 249, 0.95);
  border: 2rpx solid rgba(169, 201, 238, 0.45);
}
.avatar-fallback {
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 38rpx;
}
.info {
  display: flex;
  flex-direction: column;
  gap: 8rpx;
}
.name { font-size: 30rpx; color: #5a6d7a; font-weight: 600; }
.time { font-size: 24rpx; color: #8eadcf; }
.item-right {
  display: flex;
  align-items: baseline;
}
.level-num {
  font-size: 40rpx;
  font-weight: 800;
  color: #6fb868;
  letter-spacing: 0.02em;
}
.level-unit {
  font-size: 26rpx;
  color: #6fb868;
  margin-left: 6rpx;
  font-weight: 500;
  opacity: 0.9;
}
</style>
