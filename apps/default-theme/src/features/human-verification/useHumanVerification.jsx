import { useEffect, useRef, useState } from 'react';
import { getSdkClient } from '../../app/sdk-client.js';
import { Button, FormDialog, TextInput } from '../../ui/index.js';

const SCRIPT_URL = 'https://static.geetest.com/v4/gt4.js';
let scriptPromise;

function loadGeetest() {
  if (typeof window.initGeetest4 === 'function') return Promise.resolve();
  if (scriptPromise) return scriptPromise;
  scriptPromise = new Promise((resolve, reject) => {
    const script = document.createElement('script');
    const timer = window.setTimeout(() => fail(), 10000);
    const fail = () => {
      window.clearTimeout(timer);
      script.remove();
      scriptPromise = undefined;
      reject(new Error('验证组件加载失败，请稍后重试。'));
    };
    script.src = SCRIPT_URL;
    script.async = true;
    script.onload = () => {
      window.clearTimeout(timer);
      if (typeof window.initGeetest4 === 'function') resolve();
      else fail();
    };
    script.onerror = fail;
    document.head.append(script);
  });
  return scriptPromise;
}

export async function verifyGeetest(captchaId) {
  if (!captchaId) throw new Error('验证服务配置不完整，请稍后重试。');
  await loadGeetest();
  return new Promise((resolve, reject) => {
    let instance;
    let settled = false;
    let timer = window.setTimeout(() => finish(null, new Error('验证组件响应超时，请重试。')), 10000);
    const finish = (result, error) => {
      if (settled) return;
      settled = true;
      window.clearTimeout(timer);
      instance?.destroy?.();
      if (error) reject(error);
      else resolve(result);
    };
    try {
      window.initGeetest4({ captchaId, product: 'bind', language: 'zho', timeout: 10000, protocol: 'https://' }, (captcha) => {
        if (settled) { captcha.destroy?.(); return; }
        instance = captcha;
        captcha.onReady(() => {
          window.clearTimeout(timer);
          timer = window.setTimeout(() => finish(null, new Error('验证已超时，请重试。')), 120000);
          try { captcha.showCaptcha(); }
          catch { finish(null, new Error('验证窗口打开失败，请重试。')); }
        });
        captcha.onSuccess(() => {
          const value = captcha.getValidate();
          if (!value || ['lot_number', 'captcha_output', 'pass_token', 'gen_time'].some((field) => !value[field])) {
            finish(null, new Error('验证结果不完整，请重试。'));
            return;
          }
          finish({ lot_number: value.lot_number, captcha_output: value.captcha_output, pass_token: value.pass_token, gen_time: value.gen_time });
        });
        captcha.onFail(() => finish(null, new Error('验证未通过，请重试。')));
        captcha.onError(() => finish(null, new Error('验证服务暂时不可用，请稍后重试。')));
        captcha.onClose(() => finish(null, new Error('请完成验证后再提交。')));
      });
    } catch {
      finish(null, new Error('验证组件无法启动，请重试。'));
    }
  });
}

export function useHumanVerification() {
  const [image, setImage] = useState(null);
  const [code, setCode] = useState('');
  const [imageError, setImageError] = useState('');
  const pending = useRef(null);

  useEffect(() => () => {
    pending.current?.reject(new Error('验证已取消。'));
    pending.current = null;
  }, []);

  const close = () => {
    pending.current?.reject(new Error('请完成验证后再提交。'));
    pending.current = null;
    setImage(null);
    setCode('');
  };

  const refresh = async () => {
    try {
      const next = await getSdkClient().captcha.challenge();
      if (next.driver !== 'gd_image' || !next.image_id || !next.image_base64) throw new Error();
      setImage(next);
      setCode('');
      setImageError('');
    } catch {
      setImageError('图片加载失败，请重试。');
    }
  };

  const verify = async (policy) => {
    if (!policy?.human_verification?.enabled) return {};
    if (!policy.human_verification.available) throw new Error('人机验证服务尚未就绪，请稍后重试。');
    const challenge = await getSdkClient().captcha.challenge();
    if (challenge.driver === 'geetest_v4') return verifyGeetest(challenge.captcha_id);
    if (challenge.driver !== 'gd_image' || !challenge.image_id || !challenge.image_base64) {
      throw new Error('当前人机验证方式不可用，请稍后重试。');
    }
    setImage(challenge);
    setCode('');
    setImageError('');
    return new Promise((resolve, reject) => { pending.current = { resolve, reject }; });
  };

  const dialog = image && <FormDialog
    isOpen
    onOpenChange={(open) => { if (!open) close(); }}
    title="完成人机验证"
    subtitle="请输入图片中的字符，继续刚才的操作。"
    footer={<><Button type="button" label="取消" variant="ghost" size="lg" onClick={close} /><Button type="button" label="继续" variant="primary" size="lg" isDisabled={!code.trim()} onClick={() => {
      pending.current?.resolve({ captcha_id: image.image_id, captcha_code: code.trim() });
      pending.current = null;
      setImage(null);
      setCode('');
    }} /></>}
  >
    <img src={image.image_base64} alt="验证码图片" width="120" height="40" />
    <Button type="button" label="换一张" variant="ghost" size="sm" clickAction={refresh} />
    <TextInput label="图片中的字符" value={code} onChange={setCode} width="100%" autoComplete="off" />
    {imageError && <p role="alert">{imageError}</p>}
  </FormDialog>;

  return { verify, dialog };
}
