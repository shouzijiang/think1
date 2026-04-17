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
      title="信箱"
      @left-click="back"
    />

    <view class="list-wrap">
      <view v-if="loading" class="hint">加载中…</view>

      <view v-else-if="!items.length" class="empty">
        <text class="empty-text">暂无邮件</text>
      </view>

      <view v-else class="mail-list">
        <view v-for="item in items" :key="item.id" class="mail-row" @click="openDetail(item.id)">
          <view class="mail-row-left">
            <view v-if="!item.isRead" class="unread-dot" />
          </view>

          <view class="mail-row-body">
            <view class="mail-row-title">
              <text class="mail-title">{{ item.title }}</text>
            </view>
            <text v-if="item.createdAt" class="mail-time">{{ item.createdAt }}</text>
            <text v-if="item.contentPreview" class="mail-preview">{{ item.contentPreview }}</text>
          </view>
        </view>
      </view>
    </view>
  </view>
</template>

<script setup>
import { ref } from 'vue'
import { onShow } from '@dcloudio/uni-app'
import PunPageNavBar from '../../components/PunPageNavBar.vue'
import { useNavBar } from '../../composables/useNavBar'
import { api } from '../../utils/api'

const { statusBarHeight, navBarHeight, menuButtonHeight } = useNavBar()

const loading = ref(false)
const items = ref([])

function back() {
  uni.navigateBack({
    fail: () => uni.reLaunch({ url: '/pages/index/index' }),
  })
}

async function loadMails() {
  loading.value = true
  try {
    const data = await api.getMailList({ page: 1, page_size: 50 })
    items.value = Array.isArray(data?.list) ? data.list : []
  } catch (e) {
    uni.showToast({ title: e?.message || '加载失败', icon: 'none' })
  } finally {
    loading.value = false
  }
}

function openDetail(id) {
  uni.navigateTo({ url: `/pages/mail/detail?id=${id}` })
}

onShow(() => {
  loadMails()
})
</script>

<style lang="scss" scoped>
@use '../../styles/page-theme.scss' as *;

.page {
  min-height: 100vh;
  position: relative;
  padding-bottom: 80rpx;
  padding-left: 40rpx;
  padding-right: 40rpx;
  box-sizing: border-box;
  @include pt-page-background;
}

.list-wrap {
  position: relative;
  z-index: 2;
  margin-top: 28rpx;
}

.hint {
  text-align: center;
  color: $pt-muted;
  font-size: 26rpx;
  font-weight: 600;
}

.empty {
  margin-top: 120rpx;
  display: flex;
  justify-content: center;
}

.empty-text {
  color: $pt-muted;
  font-size: 28rpx;
  font-weight: 700;
}

.mail-list {
  display: flex;
  flex-direction: column;
  gap: 18rpx;
}

.mail-row {
  display: flex;
  flex-direction: row;
  gap: 18rpx;
  padding: 26rpx 22rpx;
  border-radius: 26rpx;
  background: rgba(255, 255, 255, 0.86);
  border: 2rpx solid rgba(169, 201, 238, 0.35);
  box-shadow: 0 8rpx 24rpx rgba(169, 201, 238, 0.12);
}

.mail-row-left {
  width: 34rpx;
  display: flex;
  align-items: flex-start;
}

.unread-dot {
  width: 16rpx;
  height: 16rpx;
  border-radius: 999rpx;
  margin-top: 14rpx;
  background: #ef4444;
}

.mail-row-body {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 8rpx;
}

.mail-row-title {
  display: flex;
  align-items: center;
}

.mail-title {
  font-size: 30rpx;
  font-weight: 900;
  color: $pt-text;
}

.mail-time {
  font-size: 22rpx;
  font-weight: 600;
  color: $pt-muted;
}

.mail-preview {
  font-size: 24rpx;
  font-weight: 600;
  color: #6a7f8c;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
</style>

