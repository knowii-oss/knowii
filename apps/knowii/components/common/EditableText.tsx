import { ButtonGroup, Editable, EditableInput, EditablePreview, Flex, IconButton, Input, useEditableControls } from '@chakra-ui/react';
import { FaCheck, FaEdit, FaTimes } from 'react-icons/fa';

function EditableControls() {
  const { isEditing, getSubmitButtonProps, getCancelButtonProps, getEditButtonProps } = useEditableControls();

  return isEditing ? (
    <ButtonGroup justifyContent="center" size="sm">
      <IconButton aria-label="Save" icon={<FaCheck />} {...getSubmitButtonProps()} />
      <IconButton variant="outline" aria-label="Cancel" icon={<FaTimes />} {...getCancelButtonProps()} />
    </ButtonGroup>
  ) : (
    <Flex justifyContent="center">
      <IconButton variant="outline" aria-label="edit" size="sm" icon={<FaEdit />} {...getEditButtonProps()} />
    </Flex>
  );
}

interface Props {
  defaultValue?: string;
  onSubmit: (value: string) => void;
}

function EditableText({ defaultValue, onSubmit }: Props) {
  return (
    <Editable defaultValue={defaultValue} isPreviewFocusable={false} submitOnBlur={false} onSubmit={onSubmit}>
      <EditablePreview />
      <Input as={EditableInput} />
      <EditableControls />
    </Editable>
  );
}
export default EditableText;
