-- 信息流关注者信号按作者与关注者 ID 游标分批领取。
ALTER TABLE `user_follows`
  ADD INDEX `idx_followee_follower` (`followee_id`, `follower_id`);
