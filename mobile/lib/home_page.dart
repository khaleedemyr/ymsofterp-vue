import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'dart:convert';
import 'package:http/http.dart' as http;
import 'api_config.dart';

class HomePage extends StatefulWidget {
  const HomePage({Key? key}) : super(key: key);

  @override
  State<HomePage> createState() => _HomePageState();
}

class _HomePageState extends State<HomePage> with SingleTickerProviderStateMixin {
  Map<String, dynamic>? user;
  String? quote;
  String? quoteAuthor;
  bool loading = true;
  List<Map<String, String>> pengumuman = [
    {
      'title': 'Libur Nasional',
      'desc': 'Tanggal 17 Agustus 2024 seluruh kantor tutup.'
    },
    {
      'title': 'Update Aplikasi',
      'desc': 'Versi baru YMSoft Mobile sudah tersedia di Play Store.'
    },
  ];
  late AnimationController _animController;
  late Animation<double> _cardAnim;

  @override
  void initState() {
    super.initState();
    _animController = AnimationController(vsync: this, duration: const Duration(milliseconds: 900));
    _cardAnim = CurvedAnimation(parent: _animController, curve: Curves.easeOutBack);
    _loadData();
    _animController.forward();
  }

  Future<void> _loadData() async {
    setState(() { loading = true; });
    final prefs = await SharedPreferences.getInstance();
    final userStr = prefs.getString('user');
    if (userStr != null) {
      user = jsonDecode(userStr);
    }
    // Ambil quote random
    try {
      final resp = await http.get(Uri.parse('$BASE_URL/api/quotes/of-the-day'));
      if (resp.statusCode == 200) {
        final data = jsonDecode(resp.body);
        setState(() {
          quote = data['quote'] ?? '-';
          quoteAuthor = data['author'] ?? '';
        });
      }
    } catch (_) {}
    setState(() { loading = false; });
  }

  @override
  void dispose() {
    _animController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Scaffold(
      body: Container(
        decoration: const BoxDecoration(
          gradient: LinearGradient(
            colors: [Color(0xFFe3f0ff), Color(0xFFf8fbff), Color(0xFFe3f0ff)],
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
          ),
        ),
        child: loading
            ? const Center(child: CircularProgressIndicator())
            : SafeArea(
                child: SingleChildScrollView(
                  padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 24),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      ScaleTransition(
                        scale: _cardAnim,
                        child: Card(
                          elevation: 16,
                          shadowColor: Colors.blue.withOpacity(0.2),
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(32)),
                          color: Colors.white.withOpacity(0.95),
                          child: Padding(
                            padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 28),
                            child: Row(
                              children: [
                                Hero(
                                  tag: 'avatar',
                                  child: CircleAvatar(
                                    radius: 38,
                                    backgroundColor: Colors.blue[100],
                                    backgroundImage: AssetImage('assets/images/logo-icon.png'),
                                  ),
                                ),
                                const SizedBox(width: 22),
                                Expanded(
                                  child: Column(
                                    crossAxisAlignment: CrossAxisAlignment.start,
                                    children: [
                                      Text(user?['name'] ?? '-', style: theme.textTheme.titleLarge?.copyWith(fontWeight: FontWeight.bold, color: Colors.blue[800], fontSize: 22)),
                                      const SizedBox(height: 6),
                                      Text(user?['email'] ?? '-', style: theme.textTheme.bodyMedium?.copyWith(color: Colors.grey[700])),
                                      const SizedBox(height: 6),
                                      Container(
                                        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
                                        decoration: BoxDecoration(
                                          color: Colors.blue[50],
                                          borderRadius: BorderRadius.circular(12),
                                          boxShadow: [BoxShadow(color: Colors.blue.withOpacity(0.08), blurRadius: 8, offset: const Offset(0, 2))],
                                        ),
                                        child: Text(user?['role'] ?? '-', style: const TextStyle(color: Colors.blue, fontWeight: FontWeight.w600)),
                                      ),
                                    ],
                                  ),
                                ),
                              ],
                            ),
                          ),
                        ),
                      ),
                      const SizedBox(height: 28),
                      AnimatedContainer(
                        duration: const Duration(milliseconds: 700),
                        curve: Curves.easeOutExpo,
                        decoration: BoxDecoration(
                          gradient: const LinearGradient(
                            colors: [Color(0xFFa8edea), Color(0xFFfed6e3)],
                            begin: Alignment.topLeft,
                            end: Alignment.bottomRight,
                          ),
                          borderRadius: BorderRadius.circular(24),
                          boxShadow: [
                            BoxShadow(
                              color: Colors.pink.withOpacity(0.08),
                              blurRadius: 16,
                              offset: const Offset(0, 8),
                            ),
                          ],
                        ),
                        padding: const EdgeInsets.all(24),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Row(
                              children: [
                                const Icon(Icons.format_quote, color: Colors.purple, size: 32),
                                const SizedBox(width: 8),
                                Text('Quote of the Day', style: theme.textTheme.titleMedium?.copyWith(fontWeight: FontWeight.bold, color: Colors.purple[700])),
                              ],
                            ),
                            const SizedBox(height: 12),
                            Text(
                              quote ?? '-',
                              style: const TextStyle(fontSize: 18, fontStyle: FontStyle.italic, color: Colors.black87, shadows: [Shadow(color: Colors.white, blurRadius: 8)]),
                            ),
                            const SizedBox(height: 8),
                            Align(
                              alignment: Alignment.centerRight,
                              child: Text('- ${quoteAuthor ?? ''}', style: const TextStyle(fontWeight: FontWeight.w500, color: Colors.purple)),
                            ),
                          ],
                        ),
                      ),
                      const SizedBox(height: 32),
                      Text('Pengumuman', style: theme.textTheme.titleMedium?.copyWith(fontWeight: FontWeight.bold, color: Colors.blue[800])),
                      const SizedBox(height: 12),
                      ListView.separated(
                        shrinkWrap: true,
                        physics: const NeverScrollableScrollPhysics(),
                        itemCount: pengumuman.length,
                        separatorBuilder: (_, __) => const SizedBox(height: 12),
                        itemBuilder: (context, i) {
                          final p = pengumuman[i];
                          return TweenAnimationBuilder<double>(
                            tween: Tween(begin: 0.8, end: 1),
                            duration: Duration(milliseconds: 400 + i * 100),
                            curve: Curves.elasticOut,
                            builder: (context, scale, child) => Transform.scale(
                              scale: scale,
                              child: child,
                            ),
                            child: Card(
                              elevation: 8,
                              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(18)),
                              color: Colors.white,
                              child: ListTile(
                                leading: const Icon(Icons.announcement, color: Colors.orange, size: 32, shadows: [Shadow(color: Colors.orangeAccent, blurRadius: 8)]),
                                title: Text(p['title'] ?? '', style: const TextStyle(fontWeight: FontWeight.bold)),
                                subtitle: Text(p['desc'] ?? ''),
                              ),
                            ),
                          );
                        },
                      ),
                    ],
                  ),
                ),
              ),
      ),
    );
  }
} 