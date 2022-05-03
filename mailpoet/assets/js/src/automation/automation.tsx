import ReactDOM from 'react-dom';
import { CreateTestingWorkflowButton } from './testing';
import { useMutation } from './api';
import { useCreateDatabaseMutation, useWorkflowsQuery } from './data';

function ApiCheck(): JSX.Element {
  const { data, error, isValidating } = useWorkflowsQuery();

  if (!data || isValidating) {
    return <div>Calling API...</div>;
  }

  return <div>{error ? 'API error!' : 'API OK ✓'}</div>;
}

function RecreateSchemaButton(): JSX.Element {
  const {
    trigger: createSchema,
    error,
    isMutating,
  } = useCreateDatabaseMutation();

  return (
    <div>
      <button
        type="button"
        onClick={() => createSchema()}
        disabled={isMutating}
      >
        Recreate DB schema (data will be lost)
      </button>
      {error && (
        <div>{error?.data?.message ?? 'An unknown error occurred'}</div>
      )}
    </div>
  );
}

function DeleteSchemaButton(): JSX.Element {
  const [deleteSchema, { loading, error }] = useMutation('system/database', {
    method: 'DELETE',
  });

  return (
    <div>
      <button
        type="button"
        onClick={async () => {
          await deleteSchema();
          window.location.href =
            '/wp-admin/admin.php?page=mailpoet-experimental';
        }}
        disabled={loading}
      >
        Delete DB schema & deactivate feature
      </button>
      {error && (
        <div>{error?.data?.message ?? 'An unknown error occurred'}</div>
      )}
    </div>
  );
}

function App(): JSX.Element {
  return (
    <div>
      <ApiCheck />
      <CreateTestingWorkflowButton />
      <RecreateSchemaButton />
      <DeleteSchemaButton />
    </div>
  );
}

window.addEventListener('DOMContentLoaded', () => {
  const root = document.getElementById('mailpoet_automation');
  if (root) {
    ReactDOM.render(<App />, root);
  }
});
