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
      title="共创列表"
      @left-click="back"
    >
      <template #right>
        <view class="btn-upload" @click="goUpload" :style="{ height: menuButtonHeight + 'px' }">
          <text class="btn-upload-text">上传关卡</text>
        </view>
      </template>
    </PunPageNavBar>

    <scroll-view
      class="list-wrap"
      scroll-y
      @scrolltolower="loadMore"
    >
      <view
        v-for="item in list"
        :key="item.id"
        class="card"
        @click="play(item.id)"
      >
        <view class="card-img-wrap">
          <image
            v-if="item.hintImageUrl || item.answerImageUrl"
            class="card-img"
            :src="item.answerImageUrl || item.hintImageUrl"
            mode="aspectFill"
          />
          <view v-else class="card-img-placeholder">🖼</view>
        </view>
        <view class="card-info">
          <text class="card-answer">答案：{{ item.answer || '谐音梗' }}</text>
          <text class="card-meta">{{ item.nickname || '用户' }} · {{ item.createdAt || '' }}</text>
        </view>
        <view class="card-play">
          <text class="card-play-text">去玩</text>
          <text class="card-play-arrow">→</text>
        </view>
      </view>
      <view v-if="list.length === 0 && !loading" class="empty">
        <text class="empty-text">暂无共创关卡</text>
        <view class="empty-btn" @click="goUpload">去上传</view>
      </view>
      <view v-if="loading" class="loading-wrap">
        <text class="loading-text">加载中…</text>
      </view>
    </scroll-view>
  </view>
</template>

<script setup>
import { ref } from 'vue'
import { onShow } from '@dcloudio/uni-app'
import { api } from '../../utils/api'
import { useNavBar } from '../../composables/useNavBar'
import PunPageNavBar from '../../components/PunPageNavBar.vue'
import { useWechatPageShare } from '../../composables/useWechatPageShare'

const { statusBarHeight, navBarHeight, menuButtonHeight } = useNavBar()

// #ifdef MP-WEIXIN
useWechatPageShare('共创关卡 · 谐音梗图')
// #endif

const list = ref([])
const loading = ref(false)
const page = ref(1)
const pageSize = 20
const total = ref(0)
const noMore = ref(false)

function loadList(append = false) {
  if (loading.value) return
  if (!append) {
    page.value = 1
    list.value = []
    noMore.value = false
  }
  if (noMore.value && append) return
  loading.value = true
  api.getCocreateList({ page: page.value, page_size: pageSize })
    .then((data) => {
      const rows = (data.list || []).map((item) => ({
        id: item.id,
        userId: item.userId ?? item.user_id,
        nickname: item.nickname,
        avatar: item.avatar,
        answer: item.answer,
        hintImageUrl: item.hintImageUrl ?? item.hint_image_url,
        answerImageUrl: item.answerImageUrl ?? item.answer_image_url,
        createdAt: item.createdAt ?? item.created_at,
      }))
      if (append) {
        list.value = list.value.concat(rows)
      } else {
        list.value = rows
      }
      total.value = data.total != null ? data.total : 0
      if (rows.length < pageSize) noMore.value = true
      else page.value++
    })
    .catch(() => {
      if (!append) list.value = []
    })
    .finally(() => {
      loading.value = false
    })
}

function loadMore() {
  loadList(true)
}

onShow(() => loadList())

function back() {
  uni.navigateBack({ fail: () => { uni.reLaunch({ url: '/pages/index/index' }) } })
}
function goUpload() {
  uni.navigateTo({ url: '/pages/cocreate/upload' })
}
function play(id) {
  uni.navigateTo({ url: `/pages/play/play?cocreateId=${id}` })
}
</script>

<style lang="scss" scoped>
@use '../../styles/page-theme.scss' as *;

.page {
  min-height: 100vh;
  position: relative;
  padding-bottom: 40rpx;
  padding-left: 40rpx;
  padding-right: 40rpx;
  box-sizing: border-box;
  @include pt-page-background;
}

.btn-upload {
  position: absolute;
  right: 0;
  padding: 0 28rpx;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(145deg, #a8e6a2 0%, #91d58b 100%);
  color: #fff;
  border-radius: 24rpx;
  font-size: 28rpx;
  font-weight: 600;
  box-shadow: 0 6rpx 20rpx rgba(111, 184, 104, 0.28);
  border: 2rpx solid rgba(169, 201, 238, 0.35);
}
.btn-upload-text { color: #fff; }

.list-wrap {
  position: relative;
  z-index: 2;
  height: calc(100vh - 220rpx);
}
.card {
  background: rgba(255, 255, 255, 0.95);
  border-radius: 22rpx;
  overflow: hidden;
  margin-bottom: 24rpx;
  display: flex;
  align-items: center;
  gap: 24rpx;
  padding: 20rpx;
  box-shadow: 0 6rpx 20rpx rgba(169, 201, 238, 0.14);
  border: 2rpx solid rgba(169, 201, 238, 0.4);
}
.card-img-wrap {
  width: 160rpx;
  height: 160rpx;
  border-radius: 16rpx;
  overflow: hidden;
  flex-shrink: 0;
}
.card-img {
  width: 100%;
  height: 100%;
}
.card-img-placeholder {
  width: 100%;
  height: 100%;
  background: rgba(234, 246, 249, 0.95);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 56rpx;
}
.card-info {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 8rpx;
}
.card-answer {
  font-size: 30rpx;
  font-weight: 600;
  color: #5a6d7a;
}
.card-meta {
  font-size: 24rpx;
  color: #8eadcf;
}
.card-play {
  display: flex;
  align-items: center;
  gap: 8rpx;
  color: #6fb868;
  font-size: 28rpx;
  font-weight: 600;
}
.card-play-arrow { font-size: 32rpx; }

.empty {
  padding: 80rpx 0;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 24rpx;
}
.empty-text { font-size: 28rpx; color: #8eadcf; }
.empty-btn {
  padding: 20rpx 40rpx;
  background: linear-gradient(145deg, #a8e6a2 0%, #91d58b 100%);
  color: #fff;
  border-radius: 24rpx;
  font-size: 28rpx;
  font-weight: 600;
  box-shadow: 0 6rpx 18rpx rgba(111, 184, 104, 0.22);
}
.loading-wrap {
  padding: 24rpx;
  text-align: center;
}
.loading-text { font-size: 26rpx; color: #8eadcf; }
</style>
