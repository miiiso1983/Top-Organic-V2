import 'package:flutter/material.dart';
import '../approvals/workflow_create_screen.dart';

/// Uses the server's distinct templates, approval routes and HR permissions.
class ExceptionalAdvanceScreen extends StatelessWidget {
  const ExceptionalAdvanceScreen({super.key});
  @override
  Widget build(BuildContext context) => const WorkflowCreateScreen(
    allowedTemplateCodes: ['hr_exceptional_advance', 'hr_long_advance'],
  );
}
