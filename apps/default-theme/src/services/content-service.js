import { getSdkClient } from '../app/sdk-client.js';

function clientOrThrow() {
  const client = getSdkClient();
  if (!client?.getToken()) throw new Error('请先登录后再管理内容。');
  return client;
}

export async function updateCard({ id, content, tags, cover }) {
  if (!content?.trim()) throw new Error('正文不能为空。');
  return clientOrThrow().cards.update(Number(id), {
    content: content.trim(),
    ...(tags ? { tags } : {}),
    ...(cover ? { cover } : {}),
  });
}

export async function deleteCard(id) {
  return clientOrThrow().cards.delete(Number(id));
}

export async function updateComment(id, content) {
  if (!content?.trim()) throw new Error('评论不能为空。');
  return clientOrThrow().comments.update(Number(id), { content: content.trim() });
}

export async function deleteComment(id) {
  return clientOrThrow().comments.delete(Number(id));
}

export async function loadLikes() {
  const likes = await clientOrThrow().likes.list();
  return Array.isArray(likes) ? likes : [];
}

export async function loadOwnComments() {
  const result = await clientOrThrow().comments.listOwn({ page: 1, list_rows: 50, order_desc: true, order_key: 'created_at' });
  return result?.data || [];
}

export async function updatePassword(password) {
  if (!password?.trim()) throw new Error('请输入新密码。');
  return clientOrThrow().users.updatePassword({ password });
}

export async function updateEmail(email, captcha) {
  if (!email?.trim() || !captcha?.trim()) throw new Error('请输入邮箱和验证码。');
  return clientOrThrow().users.updateEmail({ email: email.trim(), captcha: captcha.trim() });
}

export async function sendEmailCaptcha(email) {
  if (!email?.trim()) throw new Error('请输入邮箱。');
  return clientOrThrow().users.emailCaptcha({ email: email.trim() });
}
