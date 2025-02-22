import { forwardRef, useCallback, useMemo } from 'react';
import { SearchControl } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { useRef, useImperativeHandle, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { blockDefault, Icon } from '@wordpress/icons';
import { Item } from './item';
import { StepInfoPanel } from './step_info_panel';
import { StepList } from './step_list';
import { InserterListbox } from '../inserter-listbox';
import { store } from '../../store';

// See: https://github.com/WordPress/gutenberg/blob/628ae68152f572d0b395bb15c0f71b8821e7f130/packages/block-editor/src/components/inserter/menu.js

const filterItems = (value: string, item: Item[]): Item[] =>
  item.filter((step) =>
    step.title.toLowerCase().includes(value.trim().toLowerCase()),
  );

export const Inserter = forwardRef((_, ref): JSX.Element => {
  const [filterValue, setFilterValue] = useState('');
  const [hoveredItem, setHoveredItem] = useState(null);

  const { actionSteps, logicalSteps } = useSelect(
    (select) => ({
      actionSteps: select(store).getInserterActionSteps(),
      logicalSteps: select(store).getInserterLogicalSteps(),
    }),
    [],
  );

  const onHover = useCallback(
    (item) => {
      setHoveredItem(item);
    },
    [setHoveredItem],
  );

  const searchRef = useRef<HTMLInputElement>();
  useImperativeHandle(ref, () => ({
    focusSearch: () => {
      searchRef.current?.focus();
    },
  }));

  const filteredActionSteps = useMemo(
    () => filterItems(filterValue, actionSteps),
    [actionSteps, filterValue],
  );
  const filteredLogicalSteps = useMemo(
    () => filterItems(filterValue, logicalSteps),
    [filterValue, logicalSteps],
  );

  return (
    <div className="block-editor-inserter__menu">
      <div className="block-editor-inserter__main-area">
        <div className="block-editor-inserter__content">
          <SearchControl
            className="block-editor-inserter__search"
            onChange={(value: string) => {
              if (hoveredItem) setHoveredItem(null);
              setFilterValue(value);
            }}
            value={filterValue}
            label={__('Search for blocks and patterns')}
            placeholder={__('Search')}
            ref={searchRef}
          />

          <div className="block-editor-inserter__block-list">
            <InserterListbox>
              {filteredActionSteps.length > 0 && (
                <>
                  <div className="block-editor-inserter__panel-header">
                    <h2 className="block-editor-inserter__panel-title">
                      <div>Actions</div>
                    </h2>
                  </div>
                  <div className="block-editor-inserter__panel-content">
                    <StepList
                      items={filteredActionSteps}
                      onHover={onHover}
                      onSelect={() => {}}
                      label="A"
                    />
                  </div>
                </>
              )}

              {filteredLogicalSteps.length > 0 && (
                <>
                  <div className="block-editor-inserter__panel-header">
                    <h2 className="block-editor-inserter__panel-title">
                      <div>Logical</div>
                    </h2>
                  </div>
                  <div className="block-editor-inserter__panel-content">
                    <StepList
                      items={filteredLogicalSteps}
                      onHover={onHover}
                      onSelect={() => {}}
                      label="B"
                    />
                  </div>
                </>
              )}

              {filteredActionSteps.length === 0 &&
                filteredLogicalSteps.length === 0 && (
                  <div className="block-editor-inserter__no-results">
                    <Icon
                      className="block-editor-inserter__no-results-icon"
                      icon={blockDefault}
                    />
                    <p>{__('No results found.')}</p>
                  </div>
                )}
            </InserterListbox>
          </div>
        </div>
      </div>
      {hoveredItem && <StepInfoPanel item={hoveredItem} />}
    </div>
  );
});
