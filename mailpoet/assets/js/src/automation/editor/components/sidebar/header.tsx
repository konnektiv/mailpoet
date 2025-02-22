import { Button } from '@wordpress/components';
import { useDispatch } from '@wordpress/data';
import { stepSidebarKey, store, workflowSidebarKey } from '../../store';

// See: https://github.com/WordPress/gutenberg/blob/9601a33e30ba41bac98579c8d822af63dd961488/packages/edit-post/src/components/sidebar/settings-header/index.js

type Props = {
  sidebarKey: string;
};

export function Header({ sidebarKey }: Props): JSX.Element {
  const { openSidebar } = useDispatch(store);
  const openWorkflowSettings = () => openSidebar(workflowSidebarKey);
  const openStepSettings = () => openSidebar(stepSidebarKey);

  const [workflowAriaLabel, workflowActiveClass] =
    sidebarKey === workflowSidebarKey
      ? ['Workflow (selected)', 'is-active']
      : ['Workflow', ''];

  const [stepAriaLabel, stepActiveClass] =
    sidebarKey === stepSidebarKey
      ? ['Step (selected)', 'is-active']
      : ['Step', ''];

  return (
    <ul>
      <li>
        <Button
          onClick={openWorkflowSettings}
          className={`edit-post-sidebar__panel-tab ${workflowActiveClass}`}
          aria-label={workflowAriaLabel}
          data-label="Workflow"
        >
          Workflow
        </Button>
      </li>
      <li>
        <Button
          onClick={openStepSettings}
          className={`edit-post-sidebar__panel-tab ${stepActiveClass}`}
          aria-label={stepAriaLabel}
          data-label="Workflow"
        >
          Step
        </Button>
      </li>
    </ul>
  );
}
