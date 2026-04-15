<template>
  <view class="pun-page-nav">
    <view
      v-if="statusBarHeight > 0"
      class="pun-page-nav__status"
      :style="statusStyle"
    />
    <view
      class="nav-bar"
      :class="{ 'pun-page-nav-bar--inset': inset }"
      :style="{ height: `${navBarHeight}px` }"
    >
      <view
        v-if="showLeft"
        class="nav-btn"
        :style="leftBtnStyle"
        @click="onLeft"
      >
        <image
          v-if="leftIconSrc"
          class="nav-btn-img"
          :src="leftIconSrc"
          mode="aspectFit"
        />
        <text v-else class="nav-icon">{{ leftIcon }}</text>
      </view>
      <view class="nav-center">
        <slot name="title">
          <text v-if="title" class="nav-title">{{ title }}</text>
        </slot>
      </view>
      <slot name="right" />
    </view>
  </view>
</template>

<script setup>
import { computed } from 'vue'
const props = defineProps({
  /** 状态栏占位高度（px），0 则不渲染 */
  statusBarHeight: { type: Number, default: 0 },
  statusBarFullWidth: { type: Boolean, default: false },
  navBarHeight: { type: Number, required: true },
  menuButtonHeight: { type: Number, required: true },
  title: { type: String, default: '' },
  /** 左侧为图片时的地址（默认首页图）；设为空字符串则改用 leftIcon 纯文字 */
  leftIconSrc: { type: String, default: 'https://sofun.online/static/mini/home.png' },
  /** leftIconSrc 为空时展示的文字（如返回 ‹） */
  leftIcon: { type: String, default: '' },
  showLeft: { type: Boolean, default: true },
  /** 左右留白、左侧按钮 inset、返回符加大加粗（上传/表单类页可用） */
  inset: { type: Boolean, default: false },
})

const emit = defineEmits(['leftClick'])

const statusStyle = computed(() => {
  const s = { height: `${props.statusBarHeight}px` }
  if (props.statusBarFullWidth) {
    s.width = '100%'
  }
  return s
})

const leftBtnStyle = computed(() => ({
  width: `${props.menuButtonHeight}px`,
  height: `${props.menuButtonHeight}px`,
}))

function onLeft() {
  emit('leftClick')
}
</script>

<style lang="scss" scoped>
@use '../styles/page-theme.scss' as *;

.pun-page-nav__status {
  display: block;
}

/* inset 顶栏：左右留白 */
.nav-bar.pun-page-nav-bar--inset {
  padding: 0 40rpx;
  margin-bottom: 24rpx;
}
.pun-page-nav-bar--inset .nav-btn {
  left: 40rpx;
}
.pun-page-nav-bar--inset .nav-icon {
  font-size: 44rpx;
  font-weight: bold;
  line-height: 1;
}

.nav-btn-img {
  width: 60rpx;
  flex-shrink: 0;
}
</style>
