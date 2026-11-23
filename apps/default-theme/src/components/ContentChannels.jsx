import { Link } from 'react-router-dom';

const channels = [
  { to: '/', label: '推荐', id: 'recommended' },
  { to: '/explore', label: '发现', id: 'explore' },
];

export function ContentChannels({ active }) {
  return <nav className="home-channels" aria-label="内容频道">
    {channels.map((item) => <Link
      key={item.id}
      to={item.to}
      aria-current={active === item.id ? 'page' : undefined}
      className={active === item.id ? 'is-active' : ''}
    >{item.label}</Link>)}
  </nav>;
}
