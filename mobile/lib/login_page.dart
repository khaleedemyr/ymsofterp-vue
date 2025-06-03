import 'package:flutter/material.dart';
import 'package:device_info_plus/device_info_plus.dart';
import 'package:flutter/services.dart';
import 'dart:io';

class LoginPage extends StatefulWidget {
  const LoginPage({Key? key}) : super(key: key);

  @override
  State<LoginPage> createState() => _LoginPageState();
}

class _LoginPageState extends State<LoginPage> with TickerProviderStateMixin {
  final TextEditingController _usernameController = TextEditingController();
  final TextEditingController _passwordController = TextEditingController();
  bool _isLogin = true;
  String? _error;

  late AnimationController _logoController;
  late Animation<double> _logoOpacity;
  late Animation<Offset> _logoOffset;
  late AnimationController _cardController;
  late Animation<double> _cardScale;

  String? _uuid;
  String? _about;

  @override
  void initState() {
    super.initState();
    _logoController = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 900),
    );
    _logoOpacity = Tween<double>(begin: 0, end: 1).animate(
      CurvedAnimation(parent: _logoController, curve: Curves.easeOut),
    );
    _logoOffset = Tween<Offset>(begin: const Offset(0, -0.2), end: Offset.zero).animate(
      CurvedAnimation(parent: _logoController, curve: Curves.easeOut),
    );
    _cardController = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 700),
    );
    _cardScale = Tween<double>(begin: 0.95, end: 1).animate(
      CurvedAnimation(parent: _cardController, curve: Curves.easeOutBack),
    );
    _logoController.forward();
    Future.delayed(const Duration(milliseconds: 400), () {
      _cardController.forward();
    });
  }

  @override
  void dispose() {
    _logoController.dispose();
    _cardController.dispose();
    _usernameController.dispose();
    _passwordController.dispose();
    super.dispose();
  }

  void _onLogin() {
    setState(() {
      _error = null;
    });
    if (_usernameController.text.isEmpty || _passwordController.text.isEmpty) {
      setState(() {
        _error = 'Username dan password wajib diisi';
      });
      return;
    }
    Navigator.of(context).pushReplacementNamed('/home');
  }

  void _onRegister() {
    setState(() {
      _error = null;
    });
    if (_usernameController.text.isEmpty || _passwordController.text.isEmpty) {
      setState(() {
        _error = 'Username dan password wajib diisi';
      });
      return;
    }
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(content: Text('Registrasi berhasil! Silakan login.')),
    );
    setState(() {
      _isLogin = true;
    });
  }

  Future<void> _showDeviceInfo() async {
    final deviceInfo = DeviceInfoPlugin();
    String uuid = '-';
    String about = '-';
    if (Platform.isAndroid) {
      final info = await deviceInfo.androidInfo;
      uuid = info.id ?? '-';
      about = 'Model: 	${info.model}\nAndroid: ${info.version.release}';
    } else if (Platform.isIOS) {
      final info = await deviceInfo.iosInfo;
      uuid = info.identifierForVendor ?? '-';
      about = 'Model: ${info.utsname.machine}\niOS: ${info.systemVersion}';
    }
    setState(() {
      _uuid = uuid;
      _about = about;
    });
    showModalBottomSheet(
      context: context,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      builder: (context) {
        return Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text('Device Info', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 18)),
              const SizedBox(height: 16),
              Row(
                children: [
                  const Text('UUID:', style: TextStyle(fontWeight: FontWeight.w500)),
                  const SizedBox(width: 8),
                  Expanded(
                    child: Text(_uuid ?? '-', style: const TextStyle(fontFamily: 'monospace')),
                  ),
                  IconButton(
                    icon: const Icon(Icons.copy, size: 20),
                    tooltip: 'Copy UUID',
                    onPressed: () {
                      Clipboard.setData(ClipboardData(text: _uuid ?? ''));
                      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('UUID copied!')));
                    },
                  ),
                ],
              ),
              const SizedBox(height: 12),
              Text(_about ?? '-', style: const TextStyle(fontSize: 15)),
              const SizedBox(height: 8),
            ],
          ),
        );
      },
    );
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Scaffold(
      body: Container(
        width: double.infinity,
        height: double.infinity,
        decoration: const BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topCenter,
            end: Alignment.bottomCenter,
            colors: [
              Color(0xFFe3f0ff),
              Color(0xFFf8fbff),
              Color(0xFFe3f0ff),
            ],
          ),
        ),
        child: SafeArea(
          child: Center(
            child: SingleChildScrollView(
              padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 16),
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  FadeTransition(
                    opacity: _logoOpacity,
                    child: SlideTransition(
                      position: _logoOffset,
                      child: Column(
                        children: [
                          Container(
                            decoration: BoxDecoration(
                              color: Colors.white,
                              borderRadius: BorderRadius.circular(24),
                              boxShadow: [
                                BoxShadow(
                                  color: Colors.black.withOpacity(0.07),
                                  blurRadius: 16,
                                  offset: const Offset(0, 8),
                                ),
                              ],
                            ),
                            padding: const EdgeInsets.all(16),
                            child: Image.asset(
                              'assets/images/logo.png',
                              height: 64,
                              fit: BoxFit.contain,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                  const SizedBox(height: 32),
                  ScaleTransition(
                    scale: _cardScale,
                    child: Card(
                      elevation: 6,
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(20),
                      ),
                      child: Padding(
                        padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 28),
                        child: Column(
                          children: [
                            Text(
                              _isLogin ? 'Login ke YMSoft' : 'Registrasi Akun',
                              style: theme.textTheme.titleLarge?.copyWith(
                                color: Colors.blue[700],
                                fontWeight: FontWeight.w600,
                              ),
                            ),
                            const SizedBox(height: 24),
                            TextField(
                              controller: _usernameController,
                              keyboardType: TextInputType.emailAddress,
                              decoration: InputDecoration(
                                labelText: 'Email',
                                hintText: 'Email',
                                border: OutlineInputBorder(
                                  borderRadius: BorderRadius.circular(14),
                                ),
                                prefixIcon: const Icon(Icons.email_outlined),
                              ),
                            ),
                            const SizedBox(height: 16),
                            TextField(
                              controller: _passwordController,
                              obscureText: true,
                              decoration: InputDecoration(
                                labelText: 'Password',
                                border: OutlineInputBorder(
                                  borderRadius: BorderRadius.circular(14),
                                ),
                                prefixIcon: const Icon(Icons.lock_outline),
                              ),
                            ),
                            AnimatedOpacity(
                              opacity: _error != null ? 1.0 : 0.0,
                              duration: const Duration(milliseconds: 300),
                              child: _error != null
                                  ? Padding(
                                      padding: const EdgeInsets.only(top: 14),
                                      child: Text(
                                        _error!,
                                        style: const TextStyle(color: Colors.red, fontSize: 13),
                                      ),
                                    )
                                  : const SizedBox.shrink(),
                            ),
                            const SizedBox(height: 28),
                            SizedBox(
                              width: double.infinity,
                              child: ElevatedButton(
                                onPressed: _isLogin ? _onLogin : _onRegister,
                                style: ElevatedButton.styleFrom(
                                  backgroundColor: Colors.blue[700],
                                  padding: const EdgeInsets.symmetric(vertical: 16),
                                  shape: RoundedRectangleBorder(
                                    borderRadius: BorderRadius.circular(14),
                                  ),
                                  elevation: 2,
                                  shadowColor: Colors.blue[100],
                                ),
                                child: Text(
                                  _isLogin ? 'Login' : 'Registrasi',
                                  style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w600),
                                ),
                              ),
                            ),
                            const SizedBox(height: 10),
                            TextButton(
                              onPressed: () {
                                setState(() {
                                  _isLogin = !_isLogin;
                                  _error = null;
                                });
                                if (!_isLogin) {
                                  Navigator.of(context).pushReplacementNamed('/register-prep');
                                }
                              },
                              child: Text(
                                _isLogin
                                    ? 'Belum punya akun? Registrasi'
                                    : 'Sudah punya akun? Login',
                                style: TextStyle(color: Colors.blue[400]),
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
      floatingActionButton: Align(
        alignment: Alignment.bottomRight,
        child: Padding(
          padding: const EdgeInsets.only(bottom: 80, right: 18),
          child: FloatingActionButton(
            onPressed: _showDeviceInfo,
            backgroundColor: Colors.white,
            elevation: 3,
            child: const Icon(Icons.settings, color: Colors.blue, size: 28),
          ),
        ),
      ),
      floatingActionButtonLocation: FloatingActionButtonLocation.endDocked,
      bottomNavigationBar: Padding(
        padding: const EdgeInsets.only(bottom: 32, top: 8),
        child: Text(
          'Crafted with ❤️ by IT Department-Justus Group',
          textAlign: TextAlign.center,
          style: TextStyle(color: Colors.grey[500], fontSize: 13, fontWeight: FontWeight.w500),
        ),
      ),
    );
  }
} 