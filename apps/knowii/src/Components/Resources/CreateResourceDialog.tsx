import { useRef, useState } from 'react';
import {
  CommunityResource,
  CommunityResourceCollection,
  DEFAULT_TEXTAREA_ROWS,
  knowiiApiClient,
  MAX_LENGTH_COMMUNITY_RESOURCE_DESCRIPTION,
  MIN_ACTION_TIME,
  newResourceTextArticleSchema,
  ResourceLevel,
  resourceLevelSchemaOptions,
  ResourceType,
  resourceTypeSchemaOptions,
  sleep,
  urlSchema,
  useAppData,
} from '@knowii/common';
import ResourceIcon from '@/Components/Resources/ResourceIcon';
import { Dialog } from 'primereact/dialog';
import { Stepper, StepperRefAttributes } from 'primereact/stepper';
import { Button } from 'primereact/button';
import { StepperPanel } from 'primereact/stepperpanel';
import { ProgressSpinner } from 'primereact/progressspinner';
import InputLabel from '@/Components/InputLabel';
import { Dropdown } from 'primereact/dropdown';
import { InputTextarea } from 'primereact/inputtextarea';
import { InputText } from 'primereact/inputtext';
import { FaChevronLeft, FaChevronRight } from 'react-icons/fa';

export interface CreateResourceDialogSettings {
  visible: boolean;
  communityCuid: string;
  // FIXME accept either a specific collection to create the new resource into, or a list of collections to choose from
  resourceCollection?: CommunityResourceCollection;
  // FIXME make this optional
  resourceCollections: CommunityResourceCollection[];
}

interface CreateResourceDialogProps {
  settings: CreateResourceDialogSettings;
  onHide: () => void;
  onResourceCreated: (newResource: CommunityResource) => void;
}

export function CreateResourceDialog(props: CreateResourceDialogProps) {
  const appData = useAppData();
  const toast = appData.toast;

  const [loading, setLoading] = useState(false);

  // All resources go in a specific collection and have a specific type
  // If a specific resource collection was passed in, we use that
  // Otherwise, we set to null and let the user choose
  const [resourceCollection, setResourceCollection] = useState<CommunityResourceCollection | null>(
    props.settings.resourceCollection ? props.settings.resourceCollection : null,
  );
  const [resourceType, setResourceType] = useState<ResourceType>('textArticle');
  const [resourceLevel, setResourceLevel] = useState<ResourceLevel>('unknown');
  const [description, setDescription] = useState('');
  const [url, setUrl] = useState('');

  // Requirements for step 1:
  // - Select a collection
  // - Select a type of resource
  // - Select the resource level
  // TODO once there is more than one type of resource, the user should select one explicitly
  const isStepOneValid = resourceCollection && resourceType && resourceLevel;
  const isStepTwoValid =
    resourceType === 'textArticle' &&
    newResourceTextArticleSchema.safeParse({
      url,
      description,
      level: resourceLevel,
    }).success;

  const stepperRef = useRef<StepperRefAttributes>(null);

  const goToNextStep = () => {
    stepperRef.current?.nextCallback();
  };

  const goToPreviousStep = () => {
    stepperRef.current?.prevCallback();
  };

  const handleSubmit = async () => {
    setLoading(true);

    await sleep(MIN_ACTION_TIME);

    if (!resourceCollection || !resourceType) {
      return;
    }

    if (resourceType === 'textArticle') {
      const response = await knowiiApiClient.resources.createTextArticle({
        communityCuid: props.settings.communityCuid,
        // FIXME use the right collection id
        resourceCollectionCuid: resourceCollection?.cuid,
        url,
        description,
        level: resourceLevel,
      });

      if ('success' === response.type && !response.errors) {
        toast?.show({
          severity: 'success',
          summary: 'Resource collection created successfully',
        });

        if (response.data) {
          const createdResource = response.data;
          props.onResourceCreated(createdResource);
        }

        props.onHide();
        resetForm();
      } else {
        toast?.show({
          severity: 'error',
          summary: 'Failed to create the resource collection',
          detail: response.message,
        });
        // FIXME ugly workaround to force the stepper to go back to the second step
        // The problem is that at this point in time, the dialog still only contains the loading spinner
        // The stepper is gone, and will only be back once we leave this function
        // So we need to wait a bit before we can call the stepper to move to the next state
        sleep(1).then(() => {
          goToNextStep();
        });
      }
      setLoading(false);
    }
  };

  const resetForm = () => {
    setResourceCollection(null);
    setResourceType('textArticle');
    setResourceLevel('unknown');
    setDescription('');
    setUrl('');
  };

  return (
    <Dialog
      header={
        <span className="flex items-center gap-2">
          <ResourceIcon />
          New resource
        </span>
      }
      closeOnEscape={true}
      visible={props.settings.visible}
      className="w-full sm:w-[75vw] md:w-[60vw] lg:w-[40vw] xl:w-[30vw]"
      onHide={props.onHide}
      footer={null}
    >
      {loading ? (
        <div className="flex flex-row justify-center items-center">
          <ProgressSpinner />
        </div>
      ) : (
        <>
          <span>Provide the details of your new resource.</span>
          <Stepper ref={stepperRef} linear={true} orientation="horizontal">
            <StepperPanel header="Overview">
              {
                // Collection
                // Only shown if no specific collection was given
                !props.settings.resourceCollection && props.settings.resourceCollections && (
                  <div className="mt-4 col-span-6 sm:col-span-4">
                    <InputLabel htmlFor="resourceCollection">Resource collection</InputLabel>
                    <div className="p-inputgroup mt-1">
                      <Dropdown
                        id="resourceCollection"
                        value={resourceCollection}
                        options={props.settings.resourceCollections}
                        optionLabel={'name'}
                        // the optionValue is the whole resource collection object
                        onChange={(e) => setResourceCollection(e.value)}
                        placeholder="Where to store the new resource?"
                        required
                        disabled={loading}
                      />
                    </div>
                  </div>
                )
              }

              {/* Type */}
              <div className="mt-4 col-span-6 sm:col-span-4">
                <InputLabel htmlFor="resourceType">Type</InputLabel>
                <div className="p-inputgroup mt-1">
                  <Dropdown
                    id="resourceType"
                    value={resourceType}
                    options={resourceTypeSchemaOptions}
                    optionLabel={'name'}
                    optionValue={'type'}
                    onChange={(e) => setResourceType(e.value)}
                    placeholder="Select Resource Type"
                    required
                    disabled={loading}
                  />
                </div>
              </div>

              {/* Level */}
              <div className="mt-4 col-span-6 sm:col-span-4">
                <InputLabel htmlFor="resourceLevel">Level</InputLabel>
                <div className="p-inputgroup mt-1">
                  <Dropdown
                    id="resourceLevel"
                    value={resourceLevel}
                    options={resourceLevelSchemaOptions}
                    optionLabel={'name'}
                    optionValue={'level'}
                    onChange={(e) => setResourceLevel(e.value)}
                    placeholder="Select Resource Level"
                    required
                    disabled={loading}
                    itemTemplate={(option) => (
                      <div className="flex items-center gap-2">
                        <span>{option.icon}</span>
                        <span>{option.name}</span>
                      </div>
                    )}
                    valueTemplate={(option) => (
                      <div className="flex items-center gap-2">
                        <span>{option.icon}</span>
                        <span>{option.name}</span>
                      </div>
                    )}
                  />
                </div>
              </div>

              {/* Description */}
              <div className="mt-4 col-span-6 sm:col-span-4">
                <InputLabel htmlFor="resourceDescription">Description (optional)</InputLabel>
                <div className="p-inputgroup mt-1">
                  <InputTextarea
                    id="resourceDescription"
                    value={description}
                    onChange={(e) => setDescription(e.target.value)}
                    rows={DEFAULT_TEXTAREA_ROWS}
                    maxLength={MAX_LENGTH_COMMUNITY_RESOURCE_DESCRIPTION}
                    disabled={loading}
                    placeholder="Why is it useful/important? What is it about?"
                  />
                </div>
              </div>

              <div className="flex pt-4 justify-end">
                <Button onClick={goToNextStep} disabled={!isStepOneValid || loading}>
                  <span className="flex flex-row items-center gap-2">
                    Next
                    <FaChevronRight />
                  </span>
                </Button>
              </div>
            </StepperPanel>

            <StepperPanel header="Details">
              {resourceType === 'textArticle' && (
                <>
                  {/* URL */}
                  <div className="mt-4 col-span-6 sm:col-span-4">
                    <InputLabel htmlFor="resourceUrl">Link</InputLabel>
                    <div className="p-inputgroup mt-1">
                      <InputText
                        id="resourceUrl"
                        value={url}
                        onChange={(e) => setUrl(e.target.value)}
                        disabled={loading}
                        invalid={!urlSchema.safeParse({ url }).success}
                        required
                        placeholder="https://..."
                      />
                    </div>
                  </div>
                </>
              )}

              <div className="flex pt-4 justify-end gap-2">
                <Button onClick={goToPreviousStep} severity="secondary" disabled={loading}>
                  <span className="flex flex-row items-center gap-2">
                    <FaChevronLeft />
                    Back
                  </span>
                </Button>
                <Button label="Create 🚀" onClick={handleSubmit} disabled={!isStepOneValid || !isStepTwoValid || loading} />
              </div>
            </StepperPanel>
          </Stepper>
        </>
      )}
    </Dialog>
  );
}
