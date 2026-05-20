<template>
  <view v-if="items.length" class="wrong-float-layer">
    <view
      v-for="item in items"
      :key="item.id"
      class="wrong-float-item"
      :style="getWrongFloatStyle(item)"
    >
      {{ item.text }}
    </view>
  </view>
</template>

<script setup>
const props = defineProps({
  items: {
    type: Array,
    default: () => []
  }
})

function getWrongFloatStyle(item) {
  return {
    top: `${item.top}%`,
    left: `${item.left}%`,
    animationDuration: `${item.duration}ms`,
    animationDelay: `${item.delay}ms`
  }
}
</script>

<style lang="scss" scoped>
.wrong-float-layer {
  position: absolute;
  left: 0;
  right: 0;
  bottom: 20rpx;
  height: 300rpx;
  pointer-events: none;
  overflow: hidden;
  z-index: 4;
}

.wrong-float-item {
  position: absolute;
  max-width: 240rpx;
  padding: 8rpx 16rpx;
  border-radius: 999rpx;
  font-size: 24rpx;
  line-height: 1.2;
  color: rgba(185, 28, 28, 0.78);
  background: rgba(254, 226, 226, 0.62);
  border: 1rpx solid rgba(248, 113, 113, 0.3);
  box-shadow: 0 6rpx 16rpx rgba(248, 113, 113, 0.12);
  white-space: nowrap;
  text-overflow: ellipsis;
  overflow: hidden;
  animation-name: wrong-float;
  animation-timing-function: ease-in-out;
  animation-iteration-count: infinite;
  transform-origin: center center;
}

@keyframes wrong-float {
  0% {
    transform: translate3d(0, 0, 0) rotate(-2deg);
  }
  50% {
    transform: translate3d(10rpx, -12rpx, 0) rotate(2deg);
  }
  100% {
    transform: translate3d(-6rpx, -4rpx, 0) rotate(-2deg);
  }
}
</style>
