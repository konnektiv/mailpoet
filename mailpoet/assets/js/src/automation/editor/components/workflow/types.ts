export type Step = {
  id: string;
  type: 'trigger' | 'action';
  key: string;
  next_step_id?: string;
  args: Record<string, unknown>;
};

export type Workflow = {
  id?: number;
  name: string;
  status: 'active' | 'inactive';
  created_at: string;
  updated_at: string;
  steps: Record<string, Step>;
};
