import { createWriteStream } from 'node:fs';
import { access, mkdir, readFile } from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import archiver from 'archiver';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const manifestPath = path.join(root, 'theme.json');
const distPath = path.join(root, 'dist');
const packageJson = JSON.parse(await readFile(path.join(root, 'package.json'), 'utf8'));

const manifest = JSON.parse(await readFile(manifestPath, 'utf8'));
if (manifest.mode !== 'spa' || manifest.entry !== 'dist/index.html') {
  throw new Error('theme.json 必须声明 mode=spa 且 entry=dist/index.html');
}
if (manifest.version !== packageJson.version) throw new Error('theme.json 与 package.json 的版本号必须一致');
await access(path.join(root, manifest.entry));

const releaseDirectory = path.join(root, 'release');
await mkdir(releaseDirectory, { recursive: true });
const outputPath = path.join(releaseDirectory, `${manifest.id}-${manifest.version}.zip`);
const output = createWriteStream(outputPath);
const archive = archiver('zip', { zlib: { level: 9 } });

const completed = new Promise((resolve, reject) => {
  output.on('close', resolve);
  output.on('error', reject);
  archive.on('error', reject);
});

archive.pipe(output);
archive.file(manifestPath, { name: 'theme.json' });
for (const optional of ['README.md', 'preview.png']) {
  try {
    await access(path.join(root, optional));
    archive.file(path.join(root, optional), { name: optional });
  } catch {
    // Optional release metadata is omitted when absent.
  }
}
archive.directory(distPath, 'dist');
await archive.finalize();
await completed;

console.log(outputPath);
