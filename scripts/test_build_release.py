"""Release packaging contracts that do not download packages or invoke Composer."""
import importlib.util
import json
from pathlib import Path
import subprocess
import sys
import tempfile
import unittest
from unittest.mock import patch

spec = importlib.util.spec_from_file_location('wcip_build_release', Path(__file__).with_name('build-release.py'))
builder = importlib.util.module_from_spec(spec)
spec.loader.exec_module(builder)


class ReleaseBuilderTest(unittest.TestCase):
    def test_windows_composer_launcher_uses_command_interpreter(self):
        with patch.object(builder.shutil, 'which', return_value='C:/Tools/composer.BAT'), patch.object(builder.os, 'name', 'nt'), patch.dict(builder.os.environ, {'COMSPEC': 'C:/Windows/System32/cmd.exe'}):
            self.assertEqual(['C:/Windows/System32/cmd.exe', '/c', 'C:/Tools/composer.BAT'], builder.composer_command())

    def test_native_composer_executable_runs_directly(self):
        with patch.object(builder.shutil, 'which', return_value='/usr/bin/composer'), patch.object(builder.os, 'name', 'posix'):
            self.assertEqual(['/usr/bin/composer'], builder.composer_command())

    def test_missing_composer_has_actionable_error(self):
        with patch.object(builder.shutil, 'which', return_value=None):
            with self.assertRaisesRegex(FileNotFoundError, 'Composer is required'):
                builder.composer_command()

    def test_reused_vendor_platform_failure_prevents_release_zip(self):
        with tempfile.TemporaryDirectory(prefix='wcip-builder-test-') as temp:
            root = Path(temp)
            for directory in ('src', 'templates', 'assets', 'languages', 'docs', 'system-development/examples', 'print-agent', 'production/composer'):
                (root / directory).mkdir(parents=True, exist_ok=True)
            (root / 'wc-invoice-printer.php').write_text('<?php\n * Version: 1.4.5\n')
            for filename in ('README.md', 'VERIFICATION.md', 'manifest.json'):
                (root / 'system-development' / filename).write_text('')
            for filename in ('wcip_agent.py', 'config.example.json', 'README.md', 'LICENSE'):
                (root / 'print-agent' / filename).write_text('')
            for filename in ('composer.json', 'composer.lock'):
                (root / filename).write_text(json.dumps({'packages': []}))
            (root / 'production/composer/installed.json').write_text(json.dumps({'packages': [], 'dev': False}))
            argv = ['build-release.py', '--production-vendor', str(root / 'production')]
            with patch.object(builder, 'ROOT', root), patch.object(builder, 'composer_command', return_value=['composer']), patch.object(sys, 'argv', argv), patch.object(builder.subprocess, 'run', side_effect=subprocess.CalledProcessError(1, 'composer')) as run:
                with self.assertRaises(subprocess.CalledProcessError):
                    builder.main()
                self.assertEqual(['composer', 'check-platform-reqs', '--no-dev'], run.call_args.args[0])
                self.assertTrue(run.call_args.kwargs['check'])
                self.assertFalse((root / 'build/wc-invoice-printer-1.4.5.zip').exists())


if __name__ == '__main__':
    unittest.main()
