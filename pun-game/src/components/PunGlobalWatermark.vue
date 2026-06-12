<template>
  <view class="pun-wm">
    <!-- <view class="pun-wm-inner">
      <view v-for="row in ROWS" :key="row" class="pun-wm-row">
        <text v-for="col in COLS" :key="`${row}-${col}`" class="pun-wm-text"
          >「谐音梗猜一猜」</text
        >
      </view>
    </view> -->
    <view class="pun-wm-footer">
      <text class="pun-wm-footer-text">《谐音梗猜一猜》</text>
    </view>
  </view>
</template>

<script setup>
/** 平铺斜向水印，pointer-events: none 不挡点击 */
// const ROWS = 5;
// const COLS = 2;
</script>

<style lang="scss" scoped>
.pun-wm {
  position: absolute;
  inset: 0;
  z-index: 1;
  pointer-events: none;
  overflow: hidden;

  .pun-wm-inner {
    position: absolute;
    width: 150%;
    height: 150%;
    left: -25%;
    top: -25%;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    align-items: stretch;
    padding: 20rpx 0 100rpx;
    box-sizing: border-box;
    transform: rotate(-16deg);
    transform-origin: center center;
  }

  .pun-wm-row {
    display: flex;
    flex-direction: row;
    justify-content: space-around;
    align-items: center;
    width: 100%;
    flex-shrink: 0;
  }

  .pun-wm-text {
    font-size: 30rpx;
    line-height: 1.3;
    color: #fff;
    opacity: 0.1;
    white-space: nowrap;
  }

  .pun-wm-footer {
    position: absolute;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 2;
    display: flex;
    justify-content: flex-end;
    padding: 10rpx 0;
    box-sizing: border-box;
    background: linear-gradient(180deg, rgba(0, 0, 0, 0) 0%, rgba(0, 0, 0, 0.35) 100%);
  }

  .pun-wm-footer-text {
    font-size: 28rpx;
    font-weight: 700;
    line-height: 1.2;
    color: #fff;
    letter-spacing: 2rpx;
    text-shadow: 0 2rpx 8rpx rgba(0, 0, 0, 0.45), 0 0 2rpx rgba(0, 0, 0, 0.9);
    white-space: nowrap;
  }
}
</style>
